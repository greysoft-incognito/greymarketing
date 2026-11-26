<script setup lang="ts">
import type { ProviderConnection } from '@/api';
import { useRequest } from 'alova/client';
import { Plus, ShieldCheck } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { connections, providers, workspaces } from '@/api';
import ConfirmDialog from '@/components/app/ConfirmDialog.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import ConnectProviderDialog from '@/components/providers/ConnectProviderDialog.vue';
import ProviderCard from '@/components/providers/ProviderCard.vue';
import SendTestDialog from '@/components/providers/SendTestDialog.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { notifyError, toast } from '@/lib/notify';
import { useWorkspace } from '@/stores/workspace';

const workspace = useWorkspace();
const id = workspace.current!.id;

const catalog = useRequest(() => providers.catalog());
const list = useRequest(() => workspaces.connections(id));

const items = computed(() => list.data.value?.data ?? []);
const testing = ref<string | null>(null);
const editing = ref<ProviderConnection | null>(null);
const connectOpen = ref(false);
const sendTarget = ref<ProviderConnection | null>(null);
const sendOpen = ref(false);
const removing = ref<ProviderConnection | null>(null);
const removeOpen = ref(false);

const providerFor = (key: string) => catalog.data.value?.data.find(item => item.key === key);

/**
 * Keep the list in step with a saved connection, including the default flag on its siblings.
 */
function upsert(connection: ProviderConnection) {
  const rest = items.value.filter(item => item.id !== connection.id).map(item => (
    connection.is_default && item.channel === connection.channel ? { ...item, is_default: false } : item
  ));
  const index = items.value.findIndex(item => item.id === connection.id);

  rest.splice(index === -1 ? 0 : index, 0, connection);
  list.update({ data: { ...list.data.value!, data: rest } });
}

function connect(connection: ProviderConnection | null = null) {
  editing.value = connection;
  connectOpen.value = true;
}

async function test(connection: ProviderConnection) {
  testing.value = connection.id;

  try {
    const result = await connections.test(id, connection.id);

    upsert(result.data);
    toast.success(`${connection.name} works.`);
  } catch (e) {
    notifyError(e);
    await list.send();
  } finally {
    testing.value = null;
  }
}

async function makeDefault(connection: ProviderConnection) {
  try {
    upsert((await connections.update(id, connection.id, { is_default: true })).data);
    toast.success(`${connection.name} is now the default for ${connection.channel === 'sms' ? 'SMS' : 'email'}.`);
  } catch (e) {
    notifyError(e);
  }
}

function sendTest(connection: ProviderConnection) {
  sendTarget.value = connection;
  sendOpen.value = true;
}

function confirmRemove(connection: ProviderConnection) {
  removing.value = connection;
  removeOpen.value = true;
}

async function remove() {
  const connection = removing.value!;

  try {
    await connections.remove(id, connection.id);
    await list.send();
    toast.success(`${connection.name} removed.`);
  } catch (e) {
    notifyError(e);
  }
}
</script>

<template>
  <PageHeader
    title="Providers"
    description="Connect the SMS and email accounts you already use. Messages go out through your own keys and your provider bills you directly."
  >
    <template v-if="workspace.canManage" #actions>
      <Button :disabled="!catalog.data.value" @click="connect()">
        <Plus />
        Connect provider
      </Button>
    </template>
  </PageHeader>

  <Alert v-if="list.error.value" variant="destructive" class="flex items-center justify-between gap-3">
    <AlertDescription>We couldn't load your providers. {{ list.error.value.message }}</AlertDescription>
    <Button variant="secondary" size="sm" class="shrink-0" @click="list.send()">
      Try again
    </Button>
  </Alert>

  <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
    <template v-if="list.data.value">
      <ProviderCard
        v-for="connection in items"
        :key="connection.id"
        :connection="connection"
        :provider="providerFor(connection.provider)"
        :testing="testing === connection.id"
        :can-manage="workspace.canManage"
        @test="test(connection)"
        @edit="connect(connection)"
        @send-test="sendTest(connection)"
        @make-default="makeDefault(connection)"
        @remove="confirmRemove(connection)"
      />
    </template>
    <Skeleton v-for="n in (list.data.value || list.error.value ? 0 : 2)" v-else :key="n" class="h-[168px] rounded-xl" />

    <button
      v-if="list.data.value && workspace.canManage"
      type="button"
      class="grid min-h-[168px] cursor-pointer place-items-center content-center gap-2 rounded-xl bg-muted/60 p-8 text-center transition-colors hover:bg-muted disabled:cursor-default"
      :disabled="!catalog.data.value"
      @click="connect()"
    >
      <span class="grid size-9 place-items-center rounded-full bg-card text-brand shadow-card"><Plus class="size-4" /></span>
      <span class="font-semibold">{{ items.length ? 'Connect another provider' : 'Connect your first provider' }}</span>
      <span class="text-xs text-muted-foreground">
        {{ catalog.data.value?.data.map(item => item.name).join(', ') }}
      </span>
    </button>

    <p v-if="list.data.value && !items.length && !workspace.canManage" class="text-sm text-muted-foreground">
      No providers are connected yet. Ask a workspace admin to connect one.
    </p>
  </div>

  <Alert variant="info">
    <ShieldCheck />
    <AlertTitle>Your keys stay yours</AlertTitle>
    <AlertDescription>
      Keys are encrypted when saved and only the last few characters are ever shown again.
    </AlertDescription>
  </Alert>

  <ConnectProviderDialog
    v-if="catalog.data.value"
    v-model:open="connectOpen"
    :catalog="catalog.data.value.data"
    :connection="editing"
    @saved="upsert"
  />
  <SendTestDialog v-model:open="sendOpen" :connection="sendTarget" />
  <ConfirmDialog
    v-if="removing"
    v-model:open="removeOpen"
    :title="`Remove ${removing.name}?`"
    description="Its keys are deleted for good. Reports for campaigns it already sent are kept."
    action="Remove provider"
    :run="remove"
  />
</template>
