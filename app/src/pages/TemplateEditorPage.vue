<script setup lang="ts">
import type { ApiError, Channel, Contact, Template, TemplateInput } from '@/api';
import { useRequest } from 'alova/client';
import { Info, Loader2, Mail, MessageSquare, TriangleAlert } from 'lucide-vue-next';
import { computed, nextTick, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { contacts, templates } from '@/api';
import FormField from '@/components/app/FormField.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import SegmentedTabs from '@/components/app/SegmentedTabs.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Skeleton } from '@/components/ui/skeleton';
import { Textarea } from '@/components/ui/textarea';
import { contactName, formatRelative } from '@/lib/format';
import { renderTags, smsRemaining, smsSegments, STANDARD_TAGS, tagsIn } from '@/lib/merge';
import { notifyError, toast } from '@/lib/notify';
import { useWorkspace } from '@/stores/workspace';

type Field = 'subject' | 'html' | 'body';

const route = useRoute();
const router = useRouter();
const workspace = useWorkspace();
const id = workspace.current!.id;

const templateId = computed(() => route.params.template as string | undefined);
const channel = ref<Channel>(route.query.channel === 'email' ? 'email' : 'sms');
const form = reactive({ name: '', subject: '', html: '', body: '' });
const saved = ref<Template | null>(null);
const saving = ref(false);
const error = ref<ApiError | null>(null);

const channels = [
  { value: 'sms' as const, label: 'SMS', icon: MessageSquare },
  { value: 'email' as const, label: 'Email', icon: Mail },
];

const { data: loaded, error: loadError, loading, send: load } = useRequest(
  () => templates.show(id, templateId.value!),
  { immediate: !!templateId.value },
);

const { data: sampleData } = useRequest(() => contacts.list(id, { limit: 20 }));

watch(loaded, (response) => {
  if (response) {
    fill(response.data);
  }
}, { immediate: true });

function fill(template: Template) {
  saved.value = template;
  channel.value = template.channel;
  form.name = template.name;
  form.subject = template.subject ?? '';
  form.html = template.html ?? '';
  form.body = template.body ?? '';
}

const isEmail = computed(() => channel.value === 'email');

const dirty = computed(() => {
  const base = saved.value;

  return form.name !== (base?.name ?? '')
    || form.body !== (base?.body ?? '')
    || (isEmail.value && (form.subject !== (base?.subject ?? '') || form.html !== (base?.html ?? '')));
});

/**
 * Preview recipients: real contacts, plus one with nothing filled in to show the fallbacks.
 */
const samples = computed(() => sampleData.value?.data ?? []);
const previewId = ref('');
const previewContact = computed<Contact | null>(() => samples.value.find(item => item.id === previewId.value) ?? null);

watch(samples, (list) => {
  if (!previewId.value && list.length) {
    previewId.value = (list.find(item => item.first_name) ?? list[0])!.id;
  }
}, { immediate: true });

const previewData = computed<Record<string, unknown>>(() => {
  const contact = previewContact.value;

  if (!contact) {
    return { unsubscribe_url: '#unsubscribe' };
  }

  return {
    ...contact.fields,
    first_name: contact.first_name,
    last_name: contact.last_name,
    full_name: [contact.first_name, contact.last_name].filter(Boolean).join(' '),
    email: contact.email,
    phone: contact.phone,
    unsubscribe_url: '#unsubscribe',
  };
});

const asTag = (tag: string) => `{{${tag}}}`;

const escapeHtml = (value: unknown) => String(value ?? '').replace(/[&<>"']/g, char => `&#${char.charCodeAt(0)};`);

const preview = computed(() => ({
  subject: renderTags(form.subject, previewData.value),
  body: renderTags(form.body, previewData.value),
  html: form.html
    ? `<base target="_blank"><style>body{font:14px/1.5 system-ui,sans-serif;margin:16px;color:#1d1d1f}img{max-width:100%}</style>${
      renderTags(form.html, Object.fromEntries(Object.entries(previewData.value).map(([key, value]) => [key, escapeHtml(value)])))}`
    : '',
}));

const sms = computed(() => smsSegments(preview.value.body));

/**
 * Tags to offer: the standard ones, custom fields seen on contacts, and any already in the message.
 */
const tags = computed(() => {
  const custom = samples.value.flatMap(contact => Object.keys(contact.fields ?? {}));
  const used = tagsIn(`${form.subject} ${form.html} ${form.body}`);

  return [...new Set([...STANDARD_TAGS, ...custom, ...used])]
    .filter(tag => isEmail.value || tag !== 'unsubscribe_url');
});

const missingUnsubscribe = computed(() => isEmail.value && !tagsIn(`${form.html} ${form.body}`).includes('unsubscribe_url'));

const fields = ref<Partial<Record<Field, HTMLInputElement | HTMLTextAreaElement>>>({});
const lastField = ref<Field>('body');

function track(field: Field, event: FocusEvent) {
  fields.value[field] = event.target as HTMLInputElement | HTMLTextAreaElement;
  lastField.value = field;
}

/**
 * Put a merge tag where the cursor was in the last field used.
 */
async function insert(tag: string) {
  const preferred: Field = isEmail.value ? 'html' : 'body';
  const target = isEmail.value && fields.value[lastField.value] ? lastField.value : preferred;
  const element = fields.value[target];
  const text = `{{${tag}}}`;
  const value = form[target];
  const start = element?.selectionStart ?? value.length;
  const end = element?.selectionEnd ?? value.length;

  form[target] = value.slice(0, start) + text + value.slice(end);

  await nextTick();
  element?.focus();
  element?.setSelectionRange(start + text.length, start + text.length);
}

async function save() {
  saving.value = true;
  error.value = null;

  const data: TemplateInput = isEmail.value
    ? { name: form.name, subject: form.subject, html: form.html || null, body: form.body || null }
    : { name: form.name, body: form.body };

  try {
    const response = saved.value
      ? await templates.update(id, saved.value.id, data)
      : await templates.create(id, { ...data, channel: channel.value });

    fill(response.data);
    toast.success(`${response.data.name} saved.`);

    if (!templateId.value) {
      await router.replace({ name: 'templates.edit', params: { template: response.data.id } });
    }
  } catch (e) {
    error.value = notifyError(e);
  } finally {
    saving.value = false;
  }
}

const title = computed(() => saved.value?.name || (form.name.trim() || 'New template'));
const status = computed(() => {
  if (dirty.value) {
    return saved.value ? 'Unsaved changes' : 'Not saved yet';
  }

  return saved.value ? `Last saved ${formatRelative(saved.value.updated_at)}` : 'Not saved yet';
});
</script>

<template>
  <PageHeader :title="title" :description="status">
    <template #actions>
      <Button variant="secondary" as-child>
        <RouterLink :to="{ name: 'templates' }">
          Back to templates
        </RouterLink>
      </Button>
      <Button :disabled="saving || !!loadError || (!!saved && !dirty)" @click="save">
        <Loader2 v-if="saving" class="animate-spin" />
        Save template
      </Button>
    </template>
  </PageHeader>

  <Alert v-if="loadError" variant="destructive" class="flex items-center justify-between gap-3">
    <AlertDescription>We couldn't load this template. {{ loadError.message }}</AlertDescription>
    <Button variant="secondary" size="sm" class="shrink-0" @click="load">
      Try again
    </Button>
  </Alert>

  <SegmentedTabs v-if="!templateId" v-model="channel" :options="channels" label="Channel" class="justify-self-start" />

  <div v-if="loading && !saved" class="grid gap-5 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
    <Skeleton class="h-[420px] rounded-xl" />
    <Skeleton class="h-[420px] rounded-xl" />
  </div>

  <div v-else-if="!loadError" class="grid items-start gap-5 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
    <Card>
      <CardContent class="grid gap-4">
        <FormField id="template-name" label="Template name" :error="error?.field('name')">
          <Input id="template-name" v-model="form.name" maxlength="100" placeholder="Black Friday promo" />
        </FormField>

        <FormField v-if="isEmail" id="template-subject" label="Subject" :error="error?.field('subject')">
          <Input id="template-subject" v-model="form.subject" maxlength="200" placeholder="{{first_name|Hey}}, 30% off ends Sunday" @focus="track('subject', $event)" />
        </FormField>

        <div class="grid gap-2">
          <div class="flex items-center justify-between gap-2">
            <span class="text-sm font-medium">Merge tags</span>
            <span class="text-xs text-muted-foreground">Click one to insert it at the cursor</span>
          </div>
          <div class="flex flex-wrap gap-1.5">
            <button
              v-for="tag in tags"
              :key="tag"
              type="button"
              class="cursor-pointer rounded bg-brand-soft px-1.5 py-0.5 font-mono text-xs text-brand transition-opacity hover:opacity-80"
              @click="insert(tag)"
            >
              {{ asTag(tag) }}
            </button>
          </div>
        </div>

        <template v-if="isEmail">
          <FormField id="template-html" label="HTML body" :error="error?.field('html')" hint="Paste HTML from your email designer, or write simple HTML.">
            <Textarea id="template-html" v-model="form.html" rows="14" spellcheck="false" class="font-mono text-[13px]" placeholder="<p>Hi {{first_name|there}},</p>" @focus="track('html', $event)" />
          </FormField>
          <FormField id="template-text" label="Plain-text version" :error="error?.field('body')" hint="Optional. Shown by email apps that don't display HTML. Needed if there is no HTML body.">
            <Textarea id="template-text" v-model="form.body" rows="5" class="font-mono text-[13px]" @focus="track('body', $event)" />
          </FormField>
          <Alert v-if="missingUnsubscribe" variant="info">
            <Info />
            <AlertDescription>
              Add <span v-pre class="font-mono">{{unsubscribe_url}}</span> as a link so people can opt out. Every email also carries a one-click unsubscribe header.
            </AlertDescription>
          </Alert>
        </template>

        <template v-else>
          <FormField id="template-body" label="Message" :error="error?.field('body')">
            <Textarea id="template-body" v-model="form.body" rows="6" maxlength="1600" class="font-mono text-[13px]" placeholder="Hi {{first_name|there}}, ..." @focus="track('body', $event)" />
          </FormField>
          <div class="-mt-2 flex flex-wrap items-center justify-between gap-2 text-xs">
            <span class="text-muted-foreground">A fallback after <span class="font-mono">|</span> is used when a contact has no value. Counted for the preview contact, so longer names can add a segment.</span>
            <span class="font-medium tabular-nums" aria-live="polite">
              {{ sms.length }} chars · {{ sms.segments }} segment{{ sms.segments === 1 ? '' : 's' }} · {{ sms.encoding === 'gsm7' ? 'GSM-7' : 'Unicode' }}
            </span>
          </div>
          <Alert v-if="sms.segments > 1" variant="warning">
            <TriangleAlert />
            <AlertDescription>
              This message uses {{ sms.segments }} segments, so each recipient costs {{ sms.segments }}× a single SMS.
              {{ sms.encoding === 'ucs2' ? 'Emoji and some accented letters switch it to Unicode, which fits 70 characters per SMS instead of 160.' : `Trim ${sms.length - 160} characters to fit in one.` }}
            </AlertDescription>
          </Alert>
          <p v-else class="text-xs text-muted-foreground">
            {{ smsRemaining(sms) }} characters left in this segment.
          </p>
        </template>
      </CardContent>
    </Card>

    <Card class="lg:sticky lg:top-20">
      <CardHeader class="flex flex-wrap items-start justify-between gap-3">
        <div class="grid gap-1">
          <CardTitle>Preview</CardTitle>
          <CardDescription>{{ previewContact ? `As ${contactName(previewContact)} will see it` : 'With no contact details, so fallbacks show' }}</CardDescription>
        </div>
        <NativeSelect v-model="previewId" class="w-auto max-w-[200px]" aria-label="Preview as">
          <option v-for="contact in samples" :key="contact.id" :value="contact.id">
            {{ contactName(contact) }}
          </option>
          <option value="">
            Contact with no details
          </option>
        </NativeSelect>
      </CardHeader>
      <CardContent>
        <div v-if="!isEmail" class="mx-auto w-full max-w-[280px] rounded-[28px] bg-muted p-3.5">
          <div class="mb-3 border-b border-border pb-2.5 text-center text-xs text-muted-foreground">
            {{ workspace.current!.name }}
          </div>
          <div class="rounded-2xl rounded-bl-sm bg-card px-3 py-2.5 text-[13px] break-words whitespace-pre-wrap">
            {{ preview.body || 'Your message shows here.' }}
          </div>
        </div>
        <div v-else class="overflow-hidden rounded-xl bg-muted">
          <div class="grid gap-0.5 px-3.5 py-3 text-[13px]">
            <span class="font-semibold">{{ preview.subject || 'Subject' }}</span>
            <span class="text-xs text-muted-foreground">{{ workspace.current!.name }}</span>
          </div>
          <iframe
            v-if="preview.html"
            :srcdoc="preview.html"
            sandbox="allow-popups"
            title="Email preview"
            class="mx-2.5 mb-2.5 block h-[360px] w-[calc(100%-20px)] rounded-md bg-white"
          />
          <div v-else class="mx-2.5 mb-2.5 min-h-[160px] rounded-md bg-card p-4 text-sm break-words whitespace-pre-wrap">
            {{ preview.body || 'Your email shows here.' }}
          </div>
        </div>
      </CardContent>
    </Card>
  </div>
</template>
