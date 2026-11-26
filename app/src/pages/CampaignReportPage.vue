<script setup lang="ts">
import type { MessageStatus } from '@/api';
import type { BadgeVariants } from '@/components/ui/badge';
import { useIntervalFn } from '@vueuse/core';
import { usePagination, useRequest } from 'alova/client';
import { Ban } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { campaigns, forget } from '@/api';
import CampaignStatusBadge from '@/components/app/CampaignStatusBadge.vue';
import ConfirmDialog from '@/components/app/ConfirmDialog.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import PaginationFooter from '@/components/app/PaginationFooter.vue';
import RefreshButton from '@/components/app/RefreshButton.vue';
import SegmentedTabs from '@/components/app/SegmentedTabs.vue';
import StatCard from '@/components/app/StatCard.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableEmpty, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { contactName, formatDateTime, formatNumber } from '@/lib/format';
import { notifyError, toast } from '@/lib/notify';
import { useWorkspace } from '@/stores/workspace';

const route = useRoute();
const workspace = useWorkspace();
const id = workspace.current!.id;
const campaignId = route.params.campaign as string;

const MESSAGE_STYLES: Record<MessageStatus, { label: string; variant: BadgeVariants['variant'] }> = {
  pending: { label: 'Queued', variant: 'default' },
  sent: { label: 'Sent', variant: 'info' },
  delivered: { label: 'Delivered', variant: 'success' },
  failed: { label: 'Failed', variant: 'destructive' },
  skipped: { label: 'Skipped', variant: 'warning' },
};

const filter = ref<MessageStatus | 'all'>('all');
const filters = [
  { value: 'all' as const, label: 'All' },
  { value: 'delivered' as const, label: 'Delivered' },
  { value: 'sent' as const, label: 'Sent' },
  { value: 'failed' as const, label: 'Failed' },
  { value: 'pending' as const, label: 'Queued' },
  { value: 'skipped' as const, label: 'Skipped' },
];

const { data: detail, error: detailError, send: sendDetail } = useRequest(() => campaigns.show(id, campaignId));
const { data: reportData, error: reportError, send: sendReport } = useRequest(() => campaigns.report(id, campaignId));
const {
  data: rows,
  page,
  pageSize,
  total,
  pageCount,
  loading,
  refresh: refreshRows,
} = usePagination(
  (pageNumber, perPage) => campaigns.messages(id, campaignId, {
    page: pageNumber,
    limit: perPage,
    ...(filter.value === 'all' ? {} : { status: filter.value }),
  }),
  {
    watchingStates: [filter],
    initialPageSize: 25,
    total: response => response.meta.total,
    data: response => response.data,
  },
);

const campaign = computed(() => detail.value?.data ?? null);
const report = computed(() => reportData.value?.data ?? null);
const loadError = computed(() => detailError.value ?? reportError.value);
const live = computed(() => ['preparing', 'sending'].includes(report.value?.status ?? campaign.value?.status ?? ''));
const cancellable = computed(() => ['scheduled', 'preparing', 'sending'].includes(report.value?.status ?? ''));

async function refresh() {
  await Promise.all([sendDetail(), sendReport(), refreshRows()]);
}

/**
 * Keep the numbers moving while the campaign sends.
 */
const { pause, resume } = useIntervalFn(refresh, 5000, { immediate: false });

watch(live, value => (value ? resume() : pause()), { immediate: true });

const counts = computed(() => report.value?.counts ?? { pending: 0, sent: 0, delivered: 0, failed: 0, skipped: 0 });
const handedOver = computed(() => counts.value.sent + counts.value.delivered + counts.value.failed);

const funnel = computed(() => {
  const queued = report.value?.recipients ?? 0;

  return [
    { label: 'Recipients', value: queued },
    { label: `Accepted by ${campaign.value?.connection?.name ?? 'provider'}`, value: counts.value.sent + counts.value.delivered },
    { label: 'Delivered', value: counts.value.delivered },
  ].map(row => ({ ...row, width: queued ? (row.value / queued) * 100 : 0 }));
});

const subtitle = computed(() => {
  const value = campaign.value;

  if (!value) {
    return '';
  }

  const channel = value.channel === 'sms' ? 'SMS' : 'Email';
  const via = value.connection ? ` via ${value.connection.name}` : '';

  if (value.started_at) {
    return `${channel} · sent ${formatDateTime(value.started_at)}${via}`;
  }

  return value.scheduled_at ? `${channel} · scheduled for ${formatDateTime(value.scheduled_at)}${via}` : `${channel}${via}`;
});

const percent = (part: number, whole: number) => (whole ? `${Math.round((part / whole) * 1000) / 10}%` : '–');

const cancelOpen = ref(false);

async function cancel() {
  try {
    await campaigns.cancel(id, campaignId);
    await forget('campaigns.list', 'stats');
    await refresh();
    toast.success('Campaign cancelled.');
  } catch (e) {
    notifyError(e);
  }
}
</script>

<template>
  <PageHeader :title="campaign?.name ?? 'Campaign report'" :description="subtitle">
    <template #actions>
      <CampaignStatusBadge v-if="report" :status="report.status" class="mr-1" />
      <RefreshButton :refresh="refresh" />
      <Button v-if="cancellable" variant="secondary" @click="cancelOpen = true">
        <Ban />
        Cancel campaign
      </Button>
    </template>
  </PageHeader>

  <Alert v-if="loadError" variant="destructive" class="flex items-center justify-between gap-3">
    <AlertDescription>We couldn't load this report. {{ loadError.message }}</AlertDescription>
    <Button variant="secondary" size="sm" class="shrink-0" @click="refresh">
      Try again
    </Button>
  </Alert>

  <template v-if="report">
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
      <StatCard label="Recipients" :value="formatNumber(report.recipients)" :note="live ? 'Sending now' : `${formatNumber(counts.skipped)} skipped`" />
      <StatCard label="Delivered" :value="formatNumber(counts.delivered)" :note="`${report.delivery_rate}% of accepted`" />
      <StatCard label="Failed" :value="formatNumber(counts.failed)" :note="`${report.failure_rate}% of attempts`" />
      <StatCard label="Opted out" :value="formatNumber(report.opt_outs)" :note="`${percent(report.opt_outs, handedOver)} since sending`" />
    </div>

    <Card>
      <CardHeader>
        <CardTitle>Delivery funnel</CardTitle>
        <CardDescription>From provider delivery receipts. Some networks don't send receipts, so those stay at Sent.</CardDescription>
      </CardHeader>
      <CardContent class="grid gap-3">
        <div v-for="row in funnel" :key="row.label" class="grid grid-cols-[minmax(0,140px)_minmax(0,1fr)_70px] items-center gap-3 text-[13px]">
          <span class="truncate">{{ row.label }}</span>
          <div class="h-2 overflow-hidden rounded-full bg-muted">
            <div class="h-full rounded-full bg-brand transition-[width]" :style="{ width: `${row.width}%` }" />
          </div>
          <span class="text-right tabular-nums">{{ formatNumber(row.value) }}</span>
        </div>
      </CardContent>
    </Card>
  </template>
  <div v-else-if="!loadError" class="grid grid-cols-2 gap-4 lg:grid-cols-4">
    <Skeleton v-for="n in 4" :key="n" class="h-[104px] rounded-xl" />
  </div>

  <Card>
    <CardHeader class="flex flex-wrap items-center justify-between gap-3">
      <CardTitle>Recipients</CardTitle>
      <SegmentedTabs v-model="filter" :options="filters" label="Filter recipients" />
    </CardHeader>
    <CardContent class="px-2 pt-2">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Contact</TableHead>
            <TableHead class="hidden md:table-cell">
              {{ campaign?.channel === 'email' ? 'Email' : 'Phone' }}
            </TableHead>
            <TableHead>Status</TableHead>
            <TableHead class="hidden lg:table-cell">
              Details
            </TableHead>
            <TableHead class="hidden md:table-cell">
              Updated
            </TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow v-for="message in rows" :key="message.id">
            <TableCell class="font-medium">
              {{ message.contact ? contactName(message.contact) : 'Deleted contact' }}
              <span class="block font-mono text-xs font-normal text-muted-foreground md:hidden">{{ message.to }}</span>
              <span v-if="message.error" class="block max-w-[220px] truncate text-xs font-normal text-destructive lg:hidden">{{ message.error }}</span>
            </TableCell>
            <TableCell class="hidden font-mono text-[13px] md:table-cell">
              {{ message.to }}
            </TableCell>
            <TableCell>
              <Badge :variant="MESSAGE_STYLES[message.status].variant">
                {{ MESSAGE_STYLES[message.status].label }}
              </Badge>
            </TableCell>
            <TableCell class="hidden max-w-[280px] truncate text-[13px] text-muted-foreground lg:table-cell" :title="message.error ?? message.provider_message_id ?? ''">
              {{ message.error ?? message.provider_message_id ?? '–' }}
            </TableCell>
            <TableCell class="hidden text-[13px] whitespace-nowrap text-muted-foreground md:table-cell">
              {{ formatDateTime(message.delivered_at ?? message.sent_at) || '–' }}
            </TableCell>
          </TableRow>
          <TableRow v-if="loading && !rows.length">
            <TableCell :colspan="5">
              <Skeleton class="h-9" />
            </TableCell>
          </TableRow>
          <TableEmpty v-if="!loading && !rows.length" :colspan="5">
            {{ filter === 'all' ? (campaign?.status === 'scheduled' ? 'Recipients are listed once the campaign starts.' : 'No messages yet.') : 'No messages with this status.' }}
          </TableEmpty>
        </TableBody>
      </Table>
    </CardContent>
    <PaginationFooter v-model="page" :page-size="pageSize" :total="total" :page-count="pageCount" noun="messages" />
  </Card>

  <ConfirmDialog
    v-model:open="cancelOpen"
    title="Cancel this campaign?"
    description="Messages not sent yet are skipped. Messages already sent are not affected."
    action="Cancel campaign"
    :run="cancel"
  />
</template>
