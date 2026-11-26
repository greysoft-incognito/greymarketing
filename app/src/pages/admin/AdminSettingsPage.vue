<script setup lang="ts">
import type { Component } from 'vue';
import type { ApiError } from '@/api';
import { invalidateCache } from 'alova';
import { useRequest } from 'alova/client';
import { ChevronRight, ImageUp, Loader2, LogIn, RotateCcw, Settings2 } from 'lucide-vue-next';
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { admin, branding } from '@/api';
import defaultImage from '@/assets/auth-panel.svg';
import FormField from '@/components/app/FormField.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import RefreshButton from '@/components/app/RefreshButton.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { authCopy } from '@/lib/authCopy';
import { notifyError, toast } from '@/lib/notify';
import { cn } from '@/lib/utils';

type SectionKey = 'general' | 'sign-in';

interface Section {
  key: SectionKey;
  title: string;
  description: string;
  icon: Component;
}

const sections: Section[] = [
  { key: 'general', title: 'General', description: 'The app name and contact details customers see in emails and invoices.', icon: Settings2 },
  { key: 'sign-in', title: 'Sign-in pages', description: 'The image and text beside the sign-in, sign-up and invite forms.', icon: LogIn },
];

const MESSAGE_MAX = 300;
const MAX_BYTES = 5 * 1024 * 1024;

const route = useRoute();
const router = useRouter();

const active = computed<SectionKey>(() => route.query.section === 'sign-in' ? 'sign-in' : 'general');
const section = computed(() => sections.find(item => item.key === active.value)!);

function open(key: SectionKey) {
  void router.replace({ query: { ...route.query, section: key } });
}

const configMethod = () => admin.configurations();
const brandingMethod = () => branding.show();

const { data: configData, send: sendConfig } = useRequest(configMethod);
const { data: brandingData, send: sendBranding } = useRequest(brandingMethod);

const general = reactive({ app_name: '', app_email: '', invoice_address: '' });
const messages = reactive({ auth_signin_message: '', auth_signup_heading: '', auth_invite_message: '' });

const messageFields = [
  { key: 'auth_signin_message', label: 'Sign-in message', hint: 'Beside the sign-in form, above the sample delivery feed.', fallback: authCopy.sign_in },
  { key: 'auth_signup_heading', label: 'Sign-up heading', hint: 'Above the list of perks beside the sign-up form.', fallback: authCopy.sign_up },
  { key: 'auth_invite_message', label: 'Invite message', hint: 'Beside the page people open from an invite email.', fallback: authCopy.invite },
] as const;

/**
 * The saved value of a setting, as text.
 */
function saved(key: string) {
  const value = configData.value?.data.find(item => item.key === key)?.value;

  return typeof value === 'string' ? value : '';
}

watch(configData, () => {
  Object.keys(general).forEach(key => general[key as keyof typeof general] = saved(key));
  Object.keys(messages).forEach(key => messages[key as keyof typeof messages] = saved(key));
}, { immediate: true });

const dirty = computed(() => {
  const fields = active.value === 'general' ? general : messages;

  return Object.entries(fields).some(([key, value]) => value.trim() !== saved(key).trim()) || (active.value === 'sign-in' && !!picked.value);
});

const uploaded = computed(() => brandingData.value?.data.auth_image ?? null);
const picked = ref<File | null>(null);
const pickedUrl = ref<string | null>(null);
const input = ref<HTMLInputElement | null>(null);
const preview = computed(() => pickedUrl.value ?? uploaded.value ?? defaultImage);
const previewText = computed(() => messages.auth_signin_message.trim() || authCopy.sign_in);

function clearPicked() {
  if (pickedUrl.value) {
    URL.revokeObjectURL(pickedUrl.value);
  }

  picked.value = null;
  pickedUrl.value = null;

  if (input.value) {
    input.value.value = '';
  }
}

function pick(event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0];

  if (!file) {
    return;
  }

  clearPicked();

  if (!/^image\/(jpeg|png|webp)$/.test(file.type) || file.size > MAX_BYTES) {
    toast.error('Choose a JPG, PNG or WebP image up to 5 MB.');

    return;
  }

  picked.value = file;
  pickedUrl.value = URL.createObjectURL(file);
}

const busy = ref<'save' | 'reset' | null>(null);
const error = ref<ApiError | null>(null);

async function reload() {
  await invalidateCache([configMethod(), brandingMethod()]);
  await Promise.all([sendConfig(), sendBranding()]);
}

async function save() {
  busy.value = 'save';
  error.value = null;

  const fields = active.value === 'general' ? general : messages;
  const data = Object.fromEntries(Object.entries(fields).map(([key, value]) => [key, value.trim() || null]));

  try {
    await admin.saveConfigurations(data);

    if (active.value === 'sign-in' && picked.value) {
      await admin.uploadAuthImage(picked.value);
      clearPicked();
    }

    await Promise.all([sendConfig(), sendBranding()]);
    toast.success(`${section.value.title} saved.`);
  } catch (e) {
    error.value = notifyError(e);
  } finally {
    busy.value = null;
  }
}

async function resetImage() {
  busy.value = 'reset';

  try {
    toast.success((await admin.resetAuthImage()).message);
    await sendBranding();
  } catch (e) {
    notifyError(e);
  } finally {
    busy.value = null;
  }
}

onBeforeUnmount(clearPicked);
</script>

<template>
  <PageHeader title="System settings" description="Settings for the whole app. Provider and payment keys aren't here; they stay in the API's environment.">
    <template #actions>
      <RefreshButton :refresh="reload" />
    </template>
  </PageHeader>

  <Card class="grid gap-0 overflow-hidden p-0 lg:grid-cols-[300px_1fr]">
    <nav aria-label="Settings sections" class="grid content-start gap-1 bg-muted/50 p-3">
      <button
        v-for="item in sections"
        :key="item.key"
        type="button"
        :aria-current="item.key === active ? 'page' : undefined"
        :class="cn(
          'flex cursor-pointer items-center gap-3 rounded-lg p-3 text-left transition-colors',
          item.key === active ? 'bg-brand-soft' : 'hover:bg-muted',
        )"
        @click="open(item.key)"
      >
        <component :is="item.icon" :class="cn('size-4 shrink-0', item.key === active ? 'text-brand' : 'text-muted-foreground')" />
        <span class="grid min-w-0 flex-1 gap-0.5">
          <span :class="cn('text-sm font-medium', item.key === active && 'text-brand')">{{ item.title }}</span>
          <span class="text-xs text-muted-foreground">{{ item.description }}</span>
        </span>
        <ChevronRight class="size-4 shrink-0 text-muted-foreground" />
      </button>
    </nav>

    <form class="flex min-w-0 flex-col" @submit.prevent="save">
      <div class="grid gap-5 p-6">
        <div class="grid gap-1">
          <h2 class="text-lg font-semibold">
            {{ section.title }}
          </h2>
          <p class="text-sm text-muted-foreground">
            {{ section.description }}
          </p>
        </div>

        <div v-if="!configData" class="flex justify-center py-10">
          <Loader2 class="size-5 animate-spin text-muted-foreground" />
        </div>

        <div v-else-if="active === 'general'" class="grid gap-4 sm:grid-cols-2">
          <FormField id="app-name" label="App name" hint="Used in email subjects and signatures." :error="error?.field('app_name')">
            <Input id="app-name" v-model="general.app_name" maxlength="100" />
          </FormField>
          <FormField id="app-email" label="Support email" hint="Where customers are told to write for help." :error="error?.field('app_email')">
            <Input id="app-email" v-model="general.app_email" type="email" />
          </FormField>
          <FormField id="invoice-address" label="Invoice address" hint="Printed on payment invoices." class="sm:col-span-2" :error="error?.field('invoice_address')">
            <Textarea id="invoice-address" v-model="general.invoice_address" rows="3" />
          </FormField>
        </div>

        <div v-else class="grid gap-6">
          <div class="grid gap-4 sm:grid-cols-[200px_1fr] sm:items-start">
            <div class="relative aspect-[3/4] w-full max-w-[200px] overflow-hidden rounded-lg bg-ink text-ink-foreground">
              <img :src="preview" alt="Sign-in panel preview" class="absolute inset-0 size-full object-cover">
              <div class="absolute inset-0 bg-gradient-to-r from-ink/85 via-ink/55 to-ink/10" />
              <p class="relative m-0 line-clamp-6 p-4 pt-[45%] text-xs leading-normal">
                {{ previewText }}
              </p>
            </div>
            <div class="grid gap-3 text-sm">
              <div class="flex flex-wrap items-center gap-2">
                <span class="font-medium">Image</span>
                <Badge v-if="picked" variant="warning">
                  Not saved
                </Badge>
                <Badge v-else-if="uploaded" variant="brand">
                  Custom
                </Badge>
                <Badge v-else>
                  Default
                </Badge>
              </div>
              <p class="m-0 text-muted-foreground">
                A tall JPG, PNG or WebP, at least 1200 × 1600 pixels and up to 5 MB. It is cropped to fill the panel and tinted so the text stays readable.
              </p>
              <input ref="input" type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="pick">
              <div class="flex flex-wrap gap-2">
                <Button type="button" variant="secondary" :disabled="!!busy" @click="input?.click()">
                  <ImageUp />
                  {{ uploaded || picked ? 'Choose another image' : 'Choose image' }}
                </Button>
                <Button v-if="picked" type="button" variant="ghost" :disabled="!!busy" @click="clearPicked">
                  Keep current
                </Button>
                <Button v-else-if="uploaded" type="button" variant="ghost" :disabled="!!busy" @click="resetImage">
                  <Loader2 v-if="busy === 'reset'" class="animate-spin" />
                  <RotateCcw v-else />
                  Use the default
                </Button>
              </div>
              <span v-if="picked" class="truncate text-xs text-muted-foreground">{{ picked.name }}</span>
            </div>
          </div>

          <FormField
            v-for="field in messageFields"
            :id="field.key"
            :key="field.key"
            :label="field.label"
            :hint="`${field.hint} Leave empty to use the default.`"
            :error="error?.field(field.key)"
          >
            <template #aside>
              <span class="text-xs text-muted-foreground tabular-nums">{{ messages[field.key].length }} / {{ MESSAGE_MAX }}</span>
            </template>
            <Textarea :id="field.key" v-model="messages[field.key]" :placeholder="field.fallback" :maxlength="MESSAGE_MAX" rows="2" />
          </FormField>
        </div>
      </div>

      <div class="mt-auto flex justify-end gap-2 bg-muted/40 px-6 py-4">
        <Button type="submit" :disabled="!!busy || !configData || !dirty">
          <Loader2 v-if="busy === 'save'" class="animate-spin" />
          Save
        </Button>
      </div>
    </form>
  </Card>
</template>
