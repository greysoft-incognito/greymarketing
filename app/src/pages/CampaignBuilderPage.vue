<script setup lang="ts">
import type { ApiError, Campaign, CampaignInput, Channel } from '@/api';
import { useRequest, useWatcher } from 'alova/client';
import { CalendarClock, Info, Loader2, Mail, MessageSquare, Plus, Send, TriangleAlert } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { campaigns, forget, lists, templates, workspaces } from '@/api';
import FormField from '@/components/app/FormField.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import StepIndicator from '@/components/app/StepIndicator.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Progress } from '@/components/ui/progress';
import { Separator } from '@/components/ui/separator';
import { Skeleton } from '@/components/ui/skeleton';
import { formatDateTime, formatNumber } from '@/lib/format';
import { notifyError, toast } from '@/lib/notify';
import { cn } from '@/lib/utils';
import { useWorkspace } from '@/stores/workspace';

const STEPS = ['Audience', 'Message', 'Schedule', 'Review'];

const route = useRoute();
const router = useRouter();
const workspace = useWorkspace();
const id = workspace.current!.id;

const campaignId = computed(() => route.params.campaign as string | undefined);
const step = computed(() => Math.min(Math.max(Number(route.query.step) || 1, 1), campaignId.value ? 4 : 1));

const form = reactive({
  name: '',
  channel: 'sms' as Channel,
  lists: [] as string[],
  template: '',
  connection: '',
  when: 'now' as 'now' | 'later',
  scheduledAt: '',
});

const saving = ref(false);
const launching = ref(false);
const error = ref<ApiError | null>(null);

const { data: detail, error: loadError, loading, send: load } = useRequest(
  () => campaigns.show(id, campaignId.value!),
  { immediate: !!campaignId.value },
);
const { data: listData } = useRequest(() => lists.list(id));
const { data: connectionData } = useRequest(() => workspaces.connections(id));
const { data: templateData, loading: templatesLoading } = useWatcher(
  () => templates.list(id, { channel: form.channel, limit: 100 }),
  [() => form.channel],
  { immediate: true },
);

const campaign = computed<Campaign | null>(() => detail.value?.data ?? null);

watch(campaign, (value) => {
  if (!value) {
    return;
  }

  if (value.status !== 'draft' && value.status !== 'scheduled') {
    router.replace({ name: 'campaigns.report', params: { campaign: value.id } });

    return;
  }

  form.name = value.name;
  form.channel = value.channel;
  form.lists = value.lists?.map(list => list.id) ?? [];
  form.template = value.template?.id ?? '';
  form.connection = value.connection?.id ?? '';
  form.when = value.scheduled_at ? 'later' : 'now';
  form.scheduledAt = value.scheduled_at ? toLocalInput(value.scheduled_at) : '';
}, { immediate: true });

const allLists = computed(() => listData.value?.data ?? []);
const channelTemplates = computed(() => templateData.value?.data ?? []);
const channelConnections = computed(() => (connectionData.value?.data ?? []).filter(item => item.channel === form.channel));
const defaultConnection = computed(() => channelConnections.value.find(item => item.is_default) ?? null);
const template = computed(() => channelTemplates.value.find(item => item.id === form.template) ?? null);
const sendingConnection = computed(() => channelConnections.value.find(item => item.id === form.connection) ?? defaultConnection.value);
const channelLabel = computed(() => (form.channel === 'sms' ? 'SMS' : 'email'));

/**
 * The browser's time zone, since scheduled times are entered in local time.
 */
const timeZone = Intl.DateTimeFormat().resolvedOptions().timeZone;

function toLocalInput(value: string): string {
  const date = new Date(value);

  return new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
}

const scheduledIso = computed(() => (form.when === 'later' && form.scheduledAt ? new Date(form.scheduledAt).toISOString() : null));

const audience = computed(() => detail.value?.audience ?? null);
const segments = computed(() => (form.channel === 'sms' ? (template.value?.sms?.segments ?? 1) : 1));

const quota = computed(() => {
  const usage = workspace.billing?.usage[form.channel === 'sms' ? 'sms' : 'emails'];

  if (!usage || usage.limit === null) {
    return null;
  }

  return { left: Math.max(usage.limit - usage.used, 0), limit: usage.limit };
});

const overQuota = computed(() => quota.value !== null && audience.value !== null && audience.value > quota.value.left);

const problems = computed(() => [
  !form.lists.length && 'Choose at least one list.',
  !form.template && 'Choose a template.',
  !sendingConnection.value && `Connect an ${channelLabel.value === 'SMS' ? 'SMS' : 'email'} provider first.`,
  audience.value === 0 && 'None of the contacts on these lists can get this channel.',
  form.when === 'later' && !scheduledIso.value && 'Pick a date and time.',
  overQuota.value && `Your plan has ${formatNumber(quota.value!.left)} ${channelLabel.value} messages left this month.`,
].filter(Boolean) as string[]);

function input(): CampaignInput {
  return {
    name: form.name,
    lists: form.lists,
    template: form.template || null,
    connection: form.connection || null,
    scheduled_at: scheduledIso.value,
  };
}

/**
 * Save the draft, then move to another step.
 */
async function go(next: number) {
  saving.value = true;
  error.value = null;

  try {
    if (campaign.value) {
      await campaigns.update(id, campaign.value.id, input());
      await load();
      await router.replace({ query: { step: next } });
    } else {
      const response = await campaigns.create(id, { ...input(), channel: form.channel });

      await router.replace({ name: 'campaigns.edit', params: { campaign: response.data.id }, query: { step: next } });
      await load();
    }
  } catch (e) {
    error.value = notifyError(e);
  } finally {
    saving.value = false;
  }
}

async function saveDraft() {
  await go(step.value);

  if (!error.value) {
    toast.success('Draft saved.');
  }
}

async function launch() {
  launching.value = true;

  try {
    await campaigns.update(id, campaign.value!.id, input());

    const response = await campaigns.launch(id, campaign.value!.id, scheduledIso.value);

    await forget('campaigns.list', 'stats', 'billing');
    await workspace.loadBilling();
    toast.success(response.message);
    await router.push(response.data.status === 'scheduled'
      ? { name: 'campaigns' }
      : { name: 'campaigns.report', params: { campaign: response.data.id } });
  } catch (e) {
    error.value = notifyError(e);
  } finally {
    launching.value = false;
  }
}

function toggleList(listId: string, checked: boolean | 'indeterminate') {
  form.lists = checked === true ? [...form.lists, listId] : form.lists.filter(item => item !== listId);
}

function pickChannel(channel: Channel) {
  if (channel !== form.channel) {
    form.channel = channel;
    form.template = '';
    form.connection = '';
  }
}

const minSchedule = toLocalInput(new Date(Date.now() + 5 * 60000).toISOString());

const pick = (active: boolean) => cn(
  'flex cursor-pointer items-center gap-3 rounded-lg p-3 text-left transition-colors disabled:cursor-not-allowed disabled:opacity-60 [&>svg]:size-4 [&>svg]:shrink-0',
  active ? 'bg-brand-soft text-brand' : 'bg-muted hover:bg-muted/70',
);
</script>

<template>
  <PageHeader :title="campaign?.name || form.name.trim() || 'New campaign'" :description="campaign ? (campaign.status === 'scheduled' ? `Scheduled for ${formatDateTime(campaign.scheduled_at)}` : 'Draft') : 'Not saved yet'">
    <template #actions>
      <Button variant="secondary" as-child>
        <RouterLink :to="{ name: 'campaigns' }">
          Back to campaigns
        </RouterLink>
      </Button>
      <Button v-if="campaign" variant="ghost" :disabled="saving" @click="saveDraft">
        Save draft
      </Button>
    </template>
  </PageHeader>

  <Alert v-if="loadError" variant="destructive" class="flex items-center justify-between gap-3">
    <AlertDescription>We couldn't load this campaign. {{ loadError.message }}</AlertDescription>
    <Button variant="secondary" size="sm" class="shrink-0" @click="load">
      Try again
    </Button>
  </Alert>

  <StepIndicator :steps="STEPS" :current="step" />

  <div v-if="loading && !campaign" class="grid gap-5 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
    <Skeleton class="h-[380px] rounded-xl" />
    <Skeleton class="h-[300px] rounded-xl" />
  </div>

  <div v-else-if="!loadError" class="grid items-start gap-5 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
    <Card>
      <CardContent v-if="step === 1" class="grid gap-5">
        <FormField id="campaign-name" label="Campaign name" :error="error?.field('name')">
          <Input id="campaign-name" v-model="form.name" maxlength="150" placeholder="Black Friday · 30% off jollof trays" />
        </FormField>

        <div class="grid gap-2">
          <span class="text-sm font-medium">Channel</span>
          <div class="grid gap-2 sm:grid-cols-2">
            <button
              v-for="option in (['sms', 'email'] as const)"
              :key="option"
              type="button"
              :class="pick(form.channel === option)"
              :disabled="!!campaign && campaign.channel !== option"
              :aria-pressed="form.channel === option"
              @click="pickChannel(option)"
            >
              <MessageSquare v-if="option === 'sms'" />
              <Mail v-else />
              <span class="grid">
                <span class="text-sm font-semibold">{{ option === 'sms' ? 'SMS' : 'Email' }}</span>
                <span class="text-xs text-muted-foreground">
                  {{ (connectionData?.data ?? []).find(item => item.channel === option && item.is_default)?.name ?? 'No provider connected' }}
                </span>
              </span>
            </button>
          </div>
          <p v-if="campaign" class="text-xs text-muted-foreground">
            The channel can't change once a draft is saved.
          </p>
        </div>

        <div class="grid gap-2">
          <span class="text-sm font-medium">Send to</span>
          <label
            v-for="list in allLists"
            :key="list.id"
            class="flex cursor-pointer items-center gap-3 rounded-lg bg-muted px-3 py-2.5 text-sm"
          >
            <Checkbox :model-value="form.lists.includes(list.id)" class="bg-card" @update:model-value="toggleList(list.id, $event)" />
            <span class="font-medium">{{ list.name }}</span>
            <span class="ml-auto text-xs text-muted-foreground tabular-nums">{{ formatNumber(list.contacts_count ?? 0) }} contacts</span>
          </label>
          <p v-if="listData && !allLists.length" class="text-sm text-muted-foreground">
            You have no lists yet.
            <RouterLink :to="{ name: 'contacts' }" class="font-medium text-foreground underline">
              Create one on the contacts page
            </RouterLink>
            first.
          </p>
          <p class="text-xs text-muted-foreground">
            Contacts on more than one list get one message. Anyone unsubscribed from {{ channelLabel }} is left out.
          </p>
        </div>
      </CardContent>

      <CardContent v-else-if="step === 2" class="grid gap-5">
        <FormField id="campaign-template" label="Template" :error="error?.field('template')">
          <div class="flex gap-2">
            <NativeSelect id="campaign-template" v-model="form.template" class="min-w-0 flex-1" :disabled="templatesLoading">
              <option value="">
                Choose a template
              </option>
              <option v-for="item in channelTemplates" :key="item.id" :value="item.id">
                {{ item.name }}
              </option>
            </NativeSelect>
            <Button variant="secondary" as-child>
              <RouterLink :to="{ name: 'templates.new', query: { channel: form.channel } }">
                <Plus />
                New
              </RouterLink>
            </Button>
          </div>
        </FormField>

        <div v-if="template" class="grid gap-2 rounded-lg bg-muted p-4">
          <span v-if="template.subject" class="text-sm font-semibold">{{ template.subject }}</span>
          <p class="line-clamp-6 text-[13px] break-words whitespace-pre-wrap">
            {{ template.body || 'HTML email' }}
          </p>
          <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground">
            <span>{{ template.tags.length ? `Merge tags: ${template.tags.join(', ')}` : 'No merge tags' }}</span>
            <RouterLink :to="{ name: 'templates.edit', params: { template: template.id } }" class="font-medium text-foreground underline">
              Edit template
            </RouterLink>
          </div>
        </div>
        <Alert v-if="template?.sms && template.sms.segments > 1" variant="warning">
          <TriangleAlert />
          <AlertDescription>
            This message uses {{ template.sms.segments }} segments, so each recipient costs {{ template.sms.segments }}× a single SMS.
          </AlertDescription>
        </Alert>

        <FormField id="campaign-connection" label="Provider" :error="error?.field('connection')">
          <NativeSelect id="campaign-connection" v-model="form.connection">
            <option value="">
              {{ defaultConnection ? `Workspace default (${defaultConnection.name})` : 'No default provider' }}
            </option>
            <option v-for="item in channelConnections" :key="item.id" :value="item.id">
              {{ item.name }}{{ item.status === 'failed' ? ' · last test failed' : '' }}
            </option>
          </NativeSelect>
        </FormField>
        <p v-if="connectionData && !channelConnections.length" class="-mt-3 text-sm text-muted-foreground">
          No {{ channelLabel }} provider is connected.
          <RouterLink :to="{ name: 'providers' }" class="font-medium text-foreground underline">
            Connect one
          </RouterLink>
          to send this campaign.
        </p>
      </CardContent>

      <CardContent v-else-if="step === 3" class="grid gap-5">
        <div class="grid gap-2 sm:grid-cols-2">
          <button type="button" :class="pick(form.when === 'now')" :aria-pressed="form.when === 'now'" @click="form.when = 'now'">
            <Send />
            <span class="grid">
              <span class="text-sm font-semibold">Send now</span>
              <span class="text-xs text-muted-foreground">Starts within a minute</span>
            </span>
          </button>
          <button type="button" :class="pick(form.when === 'later')" :aria-pressed="form.when === 'later'" @click="form.when = 'later'">
            <CalendarClock />
            <span class="grid">
              <span class="text-sm font-semibold">Schedule</span>
              <span class="text-xs text-muted-foreground">Pick a date and time</span>
            </span>
          </button>
        </div>
        <FormField
          v-if="form.when === 'later'"
          id="campaign-when"
          label="Date and time"
          :hint="`In your time zone, ${timeZone}.`"
          :error="error?.field('scheduled_at')"
        >
          <Input id="campaign-when" v-model="form.scheduledAt" type="datetime-local" :min="minSchedule" class="sm:max-w-[280px]" />
        </FormField>
      </CardContent>

      <CardContent v-else class="grid gap-3">
        <dl class="grid gap-3 text-sm">
          <div v-for="[label, value] in [
            ['Name', form.name],
            ['Channel', `${channelLabel === 'SMS' ? 'SMS' : 'Email'} via ${sendingConnection?.name ?? 'no provider'}`],
            ['Audience', allLists.filter(list => form.lists.includes(list.id)).map(list => list.name).join(', ') || 'No lists'],
            ['Message', template ? `${template.name}${template.sms ? ` · ${template.sms.segments} segment${template.sms.segments === 1 ? '' : 's'}` : ''}` : 'No template'],
            ['When', scheduledIso ? formatDateTime(scheduledIso) : 'Right away'],
          ]" :key="label" class="grid gap-1 border-b border-border pb-3 last:border-0 sm:grid-cols-[110px_minmax(0,1fr)]">
            <dt class="text-muted-foreground">
              {{ label }}
            </dt>
            <dd class="break-words">
              {{ value }}
            </dd>
          </div>
        </dl>
        <Alert v-if="problems.length" variant="warning">
          <TriangleAlert />
          <AlertDescription>
            <ul class="grid gap-1">
              <li v-for="problem in problems" :key="problem">
                {{ problem }}
              </li>
            </ul>
          </AlertDescription>
        </Alert>
        <Alert v-else-if="sendingConnection" variant="info">
          <Info />
          <AlertDescription>
            {{ sendingConnection.name }} bills your own account for these messages.
          </AlertDescription>
        </Alert>
      </CardContent>

      <CardFooter class="justify-end gap-2">
        <Button v-if="step > 1" variant="secondary" :disabled="saving || launching" @click="go(step - 1)">
          Back
        </Button>
        <Button v-if="step < 4" :disabled="saving || !form.name.trim()" @click="go(step + 1)">
          <Loader2 v-if="saving" class="animate-spin" />
          Continue
        </Button>
        <Button v-else :disabled="launching || !!problems.length" @click="launch">
          <Loader2 v-if="launching" class="animate-spin" />
          <CalendarClock v-else-if="scheduledIso" />
          <Send v-else />
          {{ scheduledIso ? 'Schedule campaign' : 'Send campaign' }}
        </Button>
      </CardFooter>
    </Card>

    <Card class="lg:sticky lg:top-20">
      <CardHeader>
        <CardTitle>Summary</CardTitle>
      </CardHeader>
      <CardContent class="grid gap-3 text-sm">
        <div class="flex justify-between gap-3">
          <span class="text-muted-foreground">Lists</span>
          <span class="tabular-nums">{{ form.lists.length }}</span>
        </div>
        <div class="flex justify-between gap-3">
          <span class="font-semibold">Will receive</span>
          <span class="font-semibold tabular-nums">{{ audience === null ? '–' : formatNumber(audience) }}</span>
        </div>
        <p class="text-xs text-muted-foreground">
          {{ audience === null ? 'Counted once the draft is saved.' : `Subscribed to ${channelLabel}, counted once across lists.` }}
        </p>
        <template v-if="form.channel === 'sms'">
          <div class="flex justify-between gap-3">
            <span class="text-muted-foreground">Segments each</span>
            <span class="tabular-nums">{{ template ? segments : '–' }}</span>
          </div>
          <div class="flex justify-between gap-3">
            <span class="text-muted-foreground">SMS parts billed</span>
            <span class="tabular-nums">{{ template && audience !== null ? formatNumber(audience * segments) : '–' }}</span>
          </div>
        </template>
        <template v-if="quota">
          <Separator />
          <div class="flex justify-between gap-3">
            <span class="text-muted-foreground">Plan messages left</span>
            <span class="tabular-nums" :class="overQuota && 'text-destructive'">{{ formatNumber(quota.left) }} of {{ formatNumber(quota.limit) }}</span>
          </div>
          <Progress :model-value="Math.min(100, ((quota.limit - quota.left + (audience ?? 0)) / quota.limit) * 100)" />
          <p class="text-xs text-muted-foreground">
            Including this campaign. Your plan counts messages, not segments.
          </p>
        </template>
      </CardContent>
    </Card>
  </div>
</template>
