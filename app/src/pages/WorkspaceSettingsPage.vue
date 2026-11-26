<script setup lang="ts">
import type { ApiError, Channel, ProviderConnection } from '@/api';
import { invalidateCache } from 'alova';
import { useRequest } from 'alova/client';
import { Loader2, Mail, MessageSquare } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { connections, countries, workspaces } from '@/api';
import ConfirmDialog from '@/components/app/ConfirmDialog.vue';
import FormField from '@/components/app/FormField.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import RefreshButton from '@/components/app/RefreshButton.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { formatDay } from '@/lib/format';
import { notifyError, toast } from '@/lib/notify';
import { useWorkspace } from '@/stores/workspace';

const router = useRouter();
const workspace = useWorkspace();
const current = computed(() => workspace.current!);
const id = current.value.id;

const connectionsMethod = () => workspaces.connections(id);

const { data: countryList } = useRequest(() => countries.list());
const { data: connectionList, send: sendConnections, update: updateConnections } = useRequest(connectionsMethod);

const regionNames = new Intl.DisplayNames(['en'], { type: 'region' });

const countryOptions = computed(() => (countryList.value?.data ?? [])
  .map(country => ({ ...country, name: regionNames.of(country.code) ?? country.code }))
  .sort((a, b) => a.name.localeCompare(b.name)));

const name = ref('');
const country = ref<string | null>('');
const saving = ref(false);
const error = ref<ApiError | null>(null);

watch(current, (value) => {
  name.value = value.name;
  country.value = value.default_country;
}, { immediate: true });

const dialCode = computed(() => countryList.value?.data.find(item => item.code === country.value)?.dial_code);
const dirty = computed(() => name.value.trim() !== current.value.name || country.value !== current.value.default_country);

async function save() {
  saving.value = true;
  error.value = null;

  try {
    await workspace.update({ name: name.value.trim(), default_country: country.value ?? undefined });
    toast.success('Workspace settings saved.');
  } catch (e) {
    error.value = notifyError(e);
  } finally {
    saving.value = false;
  }
}

async function copyId() {
  try {
    await navigator.clipboard.writeText(id);
    toast.success('Workspace ID copied.');
  } catch {
    toast.error('Copy failed. Select the ID and copy it yourself.');
  }
}

const senders: Array<{ channel: Channel; label: string; icon: typeof Mail }> = [
  { channel: 'sms', label: 'SMS', icon: MessageSquare },
  { channel: 'email', label: 'Email', icon: Mail },
];

const switching = ref<Channel | null>(null);

function connectionsFor(channel: Channel) {
  return (connectionList.value?.data ?? []).filter(connection => connection.channel === channel);
}

function defaultFor(channel: Channel) {
  return connectionsFor(channel).find(connection => connection.is_default)?.id ?? null;
}

function senderLabel(connection: ProviderConnection) {
  const sender = connection.settings.sender_id ?? connection.settings.from_email;

  return sender ? `${connection.name} · ${sender}` : connection.name;
}

async function setDefault(channel: Channel, connectionId: string | null) {
  if (!connectionId || connectionId === defaultFor(channel)) {
    return;
  }

  switching.value = channel;

  try {
    const updated = (await connections.update(id, connectionId, { is_default: true })).data;
    const rest = connectionList.value!.data.map(connection => connection.channel === channel
      ? { ...connection, is_default: connection.id === updated.id }
      : connection);

    updateConnections({ data: { ...connectionList.value!, data: rest } });
    toast.success(`${updated.name} is now the default for ${channel === 'sms' ? 'SMS' : 'email'}.`);
  } catch (e) {
    notifyError(e);
  } finally {
    switching.value = null;
  }
}

async function reload() {
  await invalidateCache([connectionsMethod(), workspaces.list()]);
  await Promise.all([sendConnections(), workspace.load()]);
}

const confirmName = ref('');
const leaveOpen = ref(false);
const deleting = ref(false);
const onlyWorkspace = computed(() => workspace.list.length < 2);

/**
 * A paid plan still running, which ends when the workspace is deleted.
 */
const paidUntil = computed(() => {
  const subscription = workspace.billing?.subscription;

  if (!subscription || subscription.amount <= 0 || !subscription.ends_at || new Date(subscription.ends_at) <= new Date()) {
    return null;
  }

  return { plan: workspace.billing?.plan?.name ?? 'paid', ends: formatDay(subscription.ends_at) };
});

async function remove() {
  const label = current.value.name;
  const owner = current.value.is_owner;

  deleting.value = true;

  try {
    await workspace.remove();
    toast.success(owner ? `${label} deleted.` : `You left ${label}.`);
    await router.push({ name: 'dashboard' });
  } catch (e) {
    notifyError(e);
  } finally {
    deleting.value = false;
  }
}
</script>

<template>
  <PageHeader title="Workspace settings" :description="`Details that apply to everyone in ${current.name}.`">
    <template #actions>
      <Badge v-if="!workspace.canManage">
        View only · ask an admin to make changes
      </Badge>
      <RefreshButton :refresh="reload" />
    </template>
  </PageHeader>

  <div class="grid max-w-[720px] gap-5">
    <Card>
      <form class="contents" @submit.prevent="save">
        <CardHeader>
          <CardTitle>General</CardTitle>
          <CardDescription>Shown in the workspace menu and to your team.</CardDescription>
        </CardHeader>
        <CardContent class="grid gap-4">
          <FormField id="workspace-name" label="Workspace name" :error="error?.field('name')">
            <Input id="workspace-name" v-model="name" maxlength="100" :disabled="!workspace.canManage" />
          </FormField>
          <FormField
            id="workspace-country"
            label="Default country"
            :error="error?.field('default_country')"
            :hint="`Phone numbers without a country code, like 0803 412 0098, are read as ${dialCode ?? 'this country'} when you add or import contacts and when replies come in.`"
          >
            <NativeSelect id="workspace-country" v-model="country" :disabled="!workspace.canManage || !countryList">
              <option v-if="!countryList" :value="country">
                {{ regionNames.of(country ?? '') ?? country }}
              </option>
              <option v-for="option in countryOptions" :key="option.code" :value="option.code">
                {{ option.name }} ({{ option.dial_code }})
              </option>
            </NativeSelect>
          </FormField>
          <FormField id="workspace-id" label="Workspace ID" hint="Give this to support if you need help.">
            <div class="flex gap-2">
              <Input id="workspace-id" :model-value="id" readonly class="flex-1 font-mono" />
              <Button type="button" variant="secondary" @click="copyId">
                Copy
              </Button>
            </div>
          </FormField>
        </CardContent>
        <CardFooter v-if="workspace.canManage">
          <Button type="submit" :disabled="saving || !dirty || !name.trim()">
            <Loader2 v-if="saving" class="animate-spin" />
            Save changes
          </Button>
        </CardFooter>
      </form>
    </Card>

    <Card>
      <CardHeader>
        <CardTitle>Default senders</CardTitle>
        <CardDescription>New campaigns send through these unless you pick another.</CardDescription>
      </CardHeader>
      <CardContent class="grid gap-4">
        <div class="grid gap-3 sm:grid-cols-2">
          <div v-for="sender in senders" :key="sender.channel" class="grid min-w-0 gap-1.5">
            <label :for="`default-${sender.channel}`" class="flex items-center gap-1.5 text-sm font-medium">
              <component :is="sender.icon" class="size-4 text-muted-foreground" />
              {{ sender.label }}
              <Loader2 v-if="switching === sender.channel" class="size-3.5 animate-spin text-muted-foreground" />
            </label>
            <NativeSelect
              v-if="!connectionList || connectionsFor(sender.channel).length"
              :id="`default-${sender.channel}`"
              :model-value="defaultFor(sender.channel)"
              :disabled="!workspace.canManage || !connectionList || switching !== null"
              @update:model-value="setDefault(sender.channel, $event ?? null)"
            >
              <option v-for="connection in connectionsFor(sender.channel)" :key="connection.id" :value="connection.id">
                {{ senderLabel(connection) }}
              </option>
            </NativeSelect>
            <p v-else :id="`default-${sender.channel}`" class="flex h-9 items-center rounded-md bg-muted px-3 text-sm text-muted-foreground">
              No {{ sender.label === 'SMS' ? 'SMS' : 'email' }} provider connected yet.
            </p>
          </div>
        </div>
        <p class="text-xs text-muted-foreground">
          Add or test connections on <RouterLink :to="{ name: 'providers' }" class="text-foreground underline underline-offset-2">Providers</RouterLink>.
        </p>
      </CardContent>
    </Card>

    <Card v-if="current.is_owner" class="bg-destructive-soft shadow-none">
      <form class="contents" @submit.prevent="remove">
        <CardHeader>
          <CardTitle class="text-destructive">
            Delete workspace
          </CardTitle>
          <CardDescription>Removes contacts, lists, templates, campaigns, reports and provider keys for everyone. This cannot be undone.</CardDescription>
        </CardHeader>
        <CardContent class="grid gap-3">
          <template v-if="onlyWorkspace">
            <p class="text-sm text-muted-foreground">
              This is your only workspace, so it can't be deleted. Create another one from the workspace menu first.
            </p>
          </template>
          <template v-else>
            <FormField id="workspace-delete" :label="`Type ${current.name} to confirm`">
              <Input id="workspace-delete" v-model="confirmName" :placeholder="current.name" autocomplete="off" class="bg-card" />
            </FormField>
            <div class="flex flex-wrap items-center gap-3">
              <Button type="submit" variant="destructive" :disabled="deleting || confirmName.trim() !== current.name">
                <Loader2 v-if="deleting" class="animate-spin" />
                Delete workspace
              </Button>
              <span v-if="paidUntil" class="text-xs text-muted-foreground">
                Your {{ paidUntil.plan }} plan runs until {{ paidUntil.ends }} and ends with the workspace.
              </span>
            </div>
          </template>
        </CardContent>
      </form>
    </Card>

    <Card v-else class="bg-destructive-soft shadow-none">
      <CardHeader>
        <CardTitle class="text-destructive">
          Leave workspace
        </CardTitle>
        <CardDescription>You will lose access to {{ current.name }}. An admin can add you back later.</CardDescription>
      </CardHeader>
      <CardContent>
        <Button variant="destructive" @click="leaveOpen = true">
          Leave workspace
        </Button>
      </CardContent>
    </Card>
  </div>

  <ConfirmDialog
    v-model:open="leaveOpen"
    :title="`Leave ${current.name}?`"
    description="You will no longer see its contacts, campaigns or reports."
    action="Leave workspace"
    :run="remove"
  />
</template>
