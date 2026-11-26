<script setup lang="ts">
import type { ApiError, Channel, Provider, ProviderConnection } from '@/api';
import { CircleCheck, Loader2, TriangleAlert } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { connections } from '@/api';
import FormField from '@/components/app/FormField.vue';
import ProviderLogo from '@/components/app/ProviderLogo.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Dialog, DialogBody, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
import { Switch } from '@/components/ui/switch';
import { notifyError } from '@/lib/notify';
import { cn } from '@/lib/utils';
import { useWorkspace } from '@/stores/workspace';

const props = defineProps<{
  catalog: Provider[];
  connection?: ProviderConnection | null;
}>();

const emit = defineEmits<{ saved: [connection: ProviderConnection] }>();

const open = defineModel<boolean>('open', { default: false });
const workspace = useWorkspace();

const providerKey = ref('');
const channel = ref<Channel>('sms');
const name = ref('');
const isDefault = ref(true);
const credentials = ref<Record<string, string>>({});
const settings = ref<Record<string, string>>({});
const saved = ref<ProviderConnection | null>(null);
const error = ref<ApiError | null>(null);
const busy = ref<'saving' | 'testing' | null>(null);

const editing = computed(() => saved.value ?? props.connection ?? null);
const provider = computed(() => props.catalog.find(item => item.key === providerKey.value));
const channelLabel = (value: Channel) => (value === 'sms' ? 'SMS' : 'email');

/**
 * Settings that only make sense for one channel are hidden for the other.
 */
const settingFields = computed(() => (provider.value?.settings ?? []).filter((field) => {
  if (field.key.startsWith('from_')) {
    return channel.value === 'email';
  }

  if (field.key === 'sender_id' || field.key === 'route') {
    return channel.value === 'sms';
  }

  return true;
}));

watch(open, (value) => {
  if (!value) {
    return;
  }

  const current = props.connection;

  saved.value = null;
  error.value = null;
  credentials.value = {};
  settings.value = {};
  providerKey.value = current?.provider ?? props.catalog[0]?.key ?? '';
  channel.value = current?.channel ?? provider.value?.channels[0] ?? 'sms';
  name.value = current?.name ?? '';
  isDefault.value = current?.is_default ?? true;

  for (const [key, value] of Object.entries(current?.settings ?? {})) {
    settings.value[key] = value == null ? '' : String(value);
  }

  if (!current) {
    name.value = `${provider.value?.name ?? ''} (main)`;
  }
}, { immediate: true });

function pick(item: Provider) {
  const wasDefaultName = !name.value || name.value === `${provider.value?.name} (main)`;

  providerKey.value = item.key;
  credentials.value = {};

  if (!item.channels.includes(channel.value)) {
    channel.value = item.channels[0]!;
  }

  if (wasDefaultName) {
    name.value = `${item.name} (main)`;
  }
}

function filled(values: Record<string, string>) {
  return Object.fromEntries(Object.entries(values).filter(([, value]) => value.trim() !== ''));
}

async function save() {
  error.value = null;
  busy.value = 'saving';

  try {
    const data = {
      name: name.value,
      is_default: isDefault.value,
      credentials: filled(credentials.value),
      settings: Object.fromEntries(settingFields.value.map(field => [field.key, settings.value[field.key] ?? ''])),
    };

    const result = editing.value
      ? await connections.update(workspace.current!.id, editing.value.id, data)
      : await connections.create(workspace.current!.id, { ...data, provider: providerKey.value, channel: channel.value });

    saved.value = result.data;
    credentials.value = {};
    emit('saved', result.data);

    await test();
  } catch (e) {
    error.value = notifyError(e);
  } finally {
    busy.value = null;
  }
}

async function test() {
  if (!saved.value) {
    return;
  }

  busy.value = 'testing';

  try {
    saved.value = (await connections.test(workspace.current!.id, saved.value.id)).data;
  } catch {
    saved.value = { ...saved.value, status: 'failed' };
  }

  saved.value = { ...saved.value };
  emit('saved', saved.value);
}

const tested = computed(() => saved.value && busy.value !== 'testing' ? saved.value : null);
</script>

<template>
  <Dialog v-model:open="open">
    <DialogContent class="max-w-xl">
      <DialogHeader>
        <DialogTitle class="text-base font-semibold">
          {{ connection ? `Edit ${connection.name}` : 'Connect a provider' }}
        </DialogTitle>
        <DialogDescription class="text-sm text-muted-foreground">
          {{ connection ? 'Leave a key blank to keep the one you saved.' : 'Choose a provider, paste your keys, then we test them.' }}
        </DialogDescription>
      </DialogHeader>

      <DialogBody>
        <div v-if="!editing" class="grid grid-cols-[repeat(auto-fill,minmax(150px,1fr))] gap-2">
          <button
            v-for="item in catalog"
            :key="item.key"
            type="button"
            :class="cn(
              'flex cursor-pointer items-center gap-2.5 rounded-lg p-2.5 text-left transition-colors',
              item.key === providerKey ? 'bg-brand-soft ring-2 ring-brand' : 'bg-muted/60 hover:bg-muted',
            )"
            :aria-pressed="item.key === providerKey"
            @click="pick(item)"
          >
            <ProviderLogo :provider="item.key" :name="item.name" class="size-8 text-xs" />
            <span class="grid min-w-0">
              <span class="truncate text-[13px] font-medium">{{ item.name }}</span>
              <span class="text-xs text-muted-foreground">{{ item.channels.map(c => c === 'sms' ? 'SMS' : 'Email').join(' + ') }}</span>
            </span>
          </button>
        </div>

        <div v-else class="flex items-center gap-3">
          <ProviderLogo :provider="editing.provider" :name="provider?.name" />
          <div class="grid">
            <span class="font-medium">{{ provider?.name ?? editing.provider }}</span>
            <span class="text-xs text-muted-foreground">Sends {{ channelLabel(editing.channel) }}</span>
          </div>
        </div>

        <template v-if="provider">
          <div v-if="!editing && provider.channels.length > 1" class="grid gap-1.5">
            <span class="text-sm font-medium">Use it for</span>
            <div class="flex gap-2">
              <Button
                v-for="item in provider.channels"
                :key="item"
                size="sm"
                :variant="channel === item ? 'default' : 'secondary'"
                @click="channel = item"
              >
                {{ item === 'sms' ? 'SMS' : 'Email' }}
              </Button>
            </div>
            <p class="text-xs text-muted-foreground">
              Connect it again to use it for the other channel too.
            </p>
          </div>

          <Separator />

          <FormField
            v-for="field in provider.credentials"
            :id="`cred-${field.key}`"
            :key="field.key"
            :label="field.label"
            :error="error?.field(`credentials.${field.key}`)"
            :hint="editing?.credentials[field.key] ? `Saved: ${editing.credentials[field.key]}` : (field.required ? undefined : 'Optional')"
          >
            <Input
              :id="`cred-${field.key}`"
              v-model="credentials[field.key]"
              :type="field.secret ? 'password' : 'text'"
              :class="field.secret ? 'font-mono' : ''"
              autocomplete="off"
              :placeholder="editing ? 'Unchanged' : ''"
            />
          </FormField>

          <FormField
            v-for="field in settingFields"
            :id="`set-${field.key}`"
            :key="field.key"
            :label="field.label"
            :error="error?.field(`settings.${field.key}`)"
            :hint="field.required ? undefined : 'Optional'"
          >
            <Input :id="`set-${field.key}`" v-model="settings[field.key]" />
          </FormField>

          <FormField id="conn-name" label="Label" hint="Only you see this." :error="error?.field('name')">
            <Input id="conn-name" v-model="name" />
          </FormField>

          <label class="flex cursor-pointer items-center gap-2.5 text-sm">
            <Switch v-model="isDefault" />
            Use as the default for {{ channelLabel(editing?.channel ?? channel) }}
          </label>
        </template>

        <Alert v-if="busy === 'testing'">
          <Loader2 class="animate-spin" />
          <AlertTitle>Testing connection…</AlertTitle>
          <AlertDescription class="text-muted-foreground">
            Calling {{ provider?.name }} with these keys.
          </AlertDescription>
        </Alert>
        <Alert v-else-if="tested?.status === 'active'" class="bg-success-soft text-success">
          <CircleCheck />
          <AlertTitle>Connection works</AlertTitle>
          <AlertDescription>{{ provider?.name }} accepted these keys. You can send with it now.</AlertDescription>
        </Alert>
        <Alert v-else-if="tested?.status === 'failed'" variant="warning">
          <TriangleAlert />
          <AlertTitle>Saved, but the test failed</AlertTitle>
          <AlertDescription>{{ tested.last_error ?? 'The provider rejected these keys.' }} Fix the keys and save again.</AlertDescription>
        </Alert>
      </DialogBody>

      <DialogFooter>
        <Button variant="secondary" :disabled="!!busy" @click="open = false">
          {{ tested?.status === 'active' ? 'Done' : 'Cancel' }}
        </Button>
        <Button v-if="tested?.status !== 'active'" :disabled="!!busy || !provider" @click="save">
          <Loader2 v-if="busy" class="animate-spin" />
          {{ editing ? 'Save and test' : 'Connect and test' }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
