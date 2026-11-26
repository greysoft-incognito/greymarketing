<script setup lang="ts">
import type { Channel, Template } from '@/api';
import { usePagination } from 'alova/client';
import { FileText, Mail, MessageSquare, Plus } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { forget, templates } from '@/api';
import ConfirmDialog from '@/components/app/ConfirmDialog.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import PaginationFooter from '@/components/app/PaginationFooter.vue';
import RefreshButton from '@/components/app/RefreshButton.vue';
import SegmentedTabs from '@/components/app/SegmentedTabs.vue';
import TemplateCard from '@/components/templates/TemplateCard.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { notifyError, toast } from '@/lib/notify';
import { useWorkspace } from '@/stores/workspace';

const workspace = useWorkspace();
const id = workspace.current!.id;

const channel = ref<Channel | 'all'>('all');

const channels = [
  { value: 'all' as const, label: 'All' },
  { value: 'sms' as const, label: 'SMS', icon: MessageSquare },
  { value: 'email' as const, label: 'Email', icon: Mail },
];

const {
  data: rows,
  page,
  pageSize,
  total,
  pageCount,
  loading,
  error,
  refresh: refreshRows,
} = usePagination(
  (pageNumber, perPage) => templates.list(id, {
    page: pageNumber,
    limit: perPage,
    ...(channel.value === 'all' ? {} : { channel: channel.value }),
  }),
  {
    watchingStates: [channel],
    initialPageSize: 24,
    total: response => response.meta.total,
    data: response => response.data,
  },
);

const empty = computed(() => !loading.value && !error.value && !rows.value.length);
const newChannel = computed<Channel>(() => (channel.value === 'email' ? 'email' : 'sms'));

/**
 * Fetch every page again, skipping anything cached.
 */
async function refresh() {
  await forget('templates.list');
  await refreshRows();
}

const removing = ref<Template | null>(null);
const removeOpen = ref(false);

function confirmRemove(template: Template) {
  removing.value = template;
  removeOpen.value = true;
}

async function remove() {
  const template = removing.value!;

  try {
    await templates.remove(id, template.id);
    await refresh();
    toast.success(`${template.name} deleted.`);
  } catch (e) {
    notifyError(e);
  }
}
</script>

<template>
  <PageHeader title="Templates" description="Reusable messages with merge tags. Use them in any campaign.">
    <template #actions>
      <RefreshButton :refresh="refresh" />
      <Button as-child>
        <RouterLink :to="{ name: 'templates.new', query: { channel: newChannel } }">
          <Plus />
          New template
        </RouterLink>
      </Button>
    </template>
  </PageHeader>

  <SegmentedTabs v-model="channel" :options="channels" label="Filter by channel" class="justify-self-start" />

  <Alert v-if="error" variant="destructive" class="flex items-center justify-between gap-3">
    <AlertDescription>We couldn't load your templates. {{ error.message }}</AlertDescription>
    <Button variant="secondary" size="sm" class="shrink-0" @click="refresh">
      Try again
    </Button>
  </Alert>

  <div v-if="empty" class="grid place-items-center gap-3 rounded-xl bg-card px-6 py-14 text-center shadow-card">
    <span class="grid size-11 place-items-center rounded-full bg-brand-soft text-brand"><FileText class="size-5" /></span>
    <div class="grid gap-1">
      <span class="font-semibold">{{ channel === 'all' ? 'No templates yet' : `No ${channel === 'sms' ? 'SMS' : 'email'} templates yet` }}</span>
      <span class="max-w-sm text-sm text-muted-foreground">
        Save a message once and reuse it in any campaign. Merge tags like <span v-pre class="font-mono">{{first_name}}</span> fill in each contact's details.
      </span>
    </div>
    <Button as-child>
      <RouterLink :to="{ name: 'templates.new', query: { channel: newChannel } }">
        <Plus />
        New template
      </RouterLink>
    </Button>
  </div>

  <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
    <template v-if="rows.length">
      <TemplateCard v-for="template in rows" :key="template.id" :template="template" @remove="confirmRemove(template)" />
    </template>
    <template v-else-if="loading">
      <Skeleton v-for="n in 3" :key="n" class="h-[196px] rounded-xl" />
    </template>
  </div>

  <PaginationFooter v-if="(pageCount ?? 1) > 1" v-model="page" :page-size="pageSize" :total="total" :page-count="pageCount" noun="templates" class="rounded-xl bg-card shadow-card" />

  <ConfirmDialog
    v-if="removing"
    v-model:open="removeOpen"
    :title="`Delete ${removing.name}?`"
    description="Sent campaigns keep their reports. Draft or scheduled campaigns that use it will need another template before they send."
    action="Delete template"
    :run="remove"
  />
</template>
