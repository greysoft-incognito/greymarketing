<script setup lang="ts">
import type { Channel } from '@/api';
import { usePagination, useWatcher } from 'alova/client';
import { BarChart3, Plus } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { forget, reports } from '@/api';
import ChannelLabel from '@/components/app/ChannelLabel.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import PaginationFooter from '@/components/app/PaginationFooter.vue';
import ProviderLogo from '@/components/app/ProviderLogo.vue';
import RefreshButton from '@/components/app/RefreshButton.vue';
import SegmentedTabs from '@/components/app/SegmentedTabs.vue';
import StatCard from '@/components/app/StatCard.vue';
import VolumeChart from '@/components/app/VolumeChart.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableEmpty, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formatDateTime, formatNumber } from '@/lib/format';
import { useWorkspace } from '@/stores/workspace';

const workspace = useWorkspace();
const id = workspace.current!.id;

const days = ref<'7' | '30' | '90'>('30');
const channel = ref<Channel | 'all'>('all');

const periods = [
  { value: '7' as const, label: '7 days' },
  { value: '30' as const, label: '30 days' },
  { value: '90' as const, label: '90 days' },
];

const channels = [
  { value: 'all' as const, label: 'All channels' },
  { value: 'sms' as const, label: 'SMS' },
  { value: 'email' as const, label: 'Email' },
];

const filters = computed(() => ({
  days: Number(days.value),
  ...(channel.value === 'all' ? {} : { channel: channel.value }),
}));

const { data: summary, error: summaryError, send: sendSummary } = useWatcher(
  () => reports.summary(id, filters.value),
  [days, channel],
  { immediate: true },
);

const {
  data: rows,
  page,
  pageSize,
  total,
  pageCount,
  loading: rowsLoading,
  error: rowsError,
  refresh: refreshRows,
} = usePagination(
  (pageNumber, perPage) => reports.campaigns(id, { ...filters.value, page: pageNumber, limit: perPage }),
  {
    watchingStates: [days, channel],
    initialPageSize: 10,
    total: response => response.meta.total,
    data: response => response.data,
  },
);

/**
 * Fetch the report again, skipping anything cached.
 */
async function refresh() {
  await forget('reports.summary', 'reports.campaigns');
  await Promise.all([sendSummary(), refreshRows()]);
}

const report = computed(() => summary.value?.data ?? null);
const loadError = computed(() => summaryError.value ?? rowsError.value);

const attempted = computed(() => {
  const counts = report.value?.counts;

  return counts ? counts.sent + counts.delivered + counts.failed : 0;
});

const kpis = computed(() => {
  const value = report.value;

  if (!value) {
    return [];
  }

  return [
    { label: 'Messages sent', value: formatNumber(attempted.value), note: `across ${formatNumber(value.campaigns)} ${value.campaigns === 1 ? 'campaign' : 'campaigns'}` },
    { label: 'Delivered', value: formatNumber(value.counts.delivered), note: attempted.value ? `${value.delivery_rate}% of accepted` : 'no sends yet' },
    { label: 'Failed', value: formatNumber(value.counts.failed), note: attempted.value ? `${value.failure_rate}% of attempts` : 'no sends yet' },
    { label: 'Opt-outs', value: formatNumber(value.opt_outs), note: 'unsubscribes and STOP replies' },
  ];
});

const funnel = computed(() => {
  const counts = report.value?.counts;

  if (!counts) {
    return [];
  }

  return [
    { label: 'Handed to providers', value: attempted.value },
    { label: 'Accepted', value: counts.sent + counts.delivered },
    { label: 'Delivered', value: counts.delivered },
  ].map(row => ({ ...row, width: attempted.value ? (row.value / attempted.value) * 100 : 0 }));
});

const period = computed(() => periods.find(option => option.value === days.value)!.label);
</script>

<template>
  <PageHeader title="Reports" :description="`Delivery for campaigns started in the last ${period}.`">
    <template #actions>
      <RefreshButton :refresh="refresh" />
    </template>
  </PageHeader>

  <div class="flex flex-wrap items-center gap-2">
    <SegmentedTabs v-model="days" :options="periods" label="Period" />
    <SegmentedTabs v-model="channel" :options="channels" label="Channel" />
  </div>

  <Alert v-if="loadError" variant="destructive" class="flex items-center justify-between gap-3">
    <AlertDescription>We couldn't load your report. {{ loadError.message }}</AlertDescription>
    <Button variant="secondary" size="sm" class="shrink-0" @click="refresh">
      Try again
    </Button>
  </Alert>

  <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
    <template v-if="kpis.length">
      <StatCard v-for="kpi in kpis" :key="kpi.label" v-bind="kpi" />
    </template>
    <Skeleton v-for="n in (kpis.length || summaryError ? 0 : 4)" v-else :key="n" class="h-[104px] rounded-xl" />
  </div>

  <Card>
    <CardHeader>
      <CardTitle>Messages sent per day</CardTitle>
      <CardDescription>Last {{ period }}</CardDescription>
      <CardAction class="flex gap-3.5 text-xs text-muted-foreground">
        <span v-if="channel !== 'email'" class="inline-flex items-center gap-1.5"><i class="inline-block size-2.5 rounded-[3px] bg-brand-grey" />SMS</span>
        <span v-if="channel !== 'sms'" class="inline-flex items-center gap-1.5"><i class="inline-block size-2.5 rounded-[3px] bg-brand" />Email</span>
      </CardAction>
    </CardHeader>
    <CardContent>
      <VolumeChart v-if="report" :days="report.daily" />
      <Skeleton v-else class="aspect-[72/22] w-full" />
    </CardContent>
  </Card>

  <div class="grid items-start gap-5 lg:grid-cols-2">
    <Card>
      <CardHeader>
        <CardTitle>Delivery funnel</CardTitle>
        <CardDescription>From provider delivery receipts. Some networks don't send receipts, so those stay at Accepted.</CardDescription>
      </CardHeader>
      <CardContent class="grid gap-3">
        <template v-if="report">
          <div v-for="row in funnel" :key="row.label" class="grid grid-cols-[minmax(0,140px)_minmax(0,1fr)_70px] items-center gap-3 text-[13px]">
            <span class="truncate">{{ row.label }}</span>
            <div class="h-2 overflow-hidden rounded-full bg-muted">
              <div class="h-full rounded-full bg-brand transition-[width]" :style="{ width: `${row.width}%` }" />
            </div>
            <span class="text-right tabular-nums">{{ formatNumber(row.value) }}</span>
          </div>
        </template>
        <Skeleton v-for="n in (report ? 0 : 3)" v-else :key="n" class="h-5" />
      </CardContent>
    </Card>

    <Card>
      <CardHeader>
        <CardTitle>Why messages failed</CardTitle>
        <CardDescription>
          {{ report ? `${formatNumber(report.counts.failed)} not delivered · most common provider errors` : 'Most common provider errors' }}
        </CardDescription>
      </CardHeader>
      <CardContent class="grid gap-2.5">
        <template v-if="report">
          <div v-for="reason in report.failure_reasons" :key="reason.reason" class="flex items-start justify-between gap-4 text-[13px]">
            <span class="min-w-0 break-words">{{ reason.reason }}</span>
            <span class="shrink-0 tabular-nums text-muted-foreground">{{ formatNumber(reason.total) }}</span>
          </div>
          <p v-if="!report.failure_reasons.length" class="text-[13px] text-muted-foreground">
            No failed messages in this period.
          </p>
        </template>
        <Skeleton v-for="n in (report ? 0 : 3)" v-else :key="n" class="h-5" />
      </CardContent>
    </Card>
  </div>

  <Card>
    <CardHeader>
      <CardTitle>By provider</CardTitle>
      <CardDescription>Each connection's share of the messages sent</CardDescription>
    </CardHeader>
    <CardContent class="px-2 pt-2">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Connection</TableHead>
            <TableHead class="hidden sm:table-cell">
              Channel
            </TableHead>
            <TableHead class="text-right">
              Sent
            </TableHead>
            <TableHead class="text-right">
              Delivery rate
            </TableHead>
            <TableHead class="hidden text-right sm:table-cell">
              Failed
            </TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow v-for="row in report?.connections ?? []" :key="`${row.id}-${row.channel}`">
            <TableCell>
              <div class="flex items-center gap-2.5">
                <ProviderLogo v-if="row.provider" :provider="row.provider" :name="row.name ?? undefined" class="size-7 text-[11px]" />
                <span class="font-medium">{{ row.name ?? 'Workspace default' }}</span>
              </div>
            </TableCell>
            <TableCell class="hidden sm:table-cell">
              <ChannelLabel :channel="row.channel" />
            </TableCell>
            <TableCell class="text-right tabular-nums">
              {{ formatNumber(row.sent) }}
            </TableCell>
            <TableCell class="text-right tabular-nums">
              {{ row.sent ? `${row.delivery_rate}%` : '–' }}
            </TableCell>
            <TableCell class="hidden text-right tabular-nums sm:table-cell">
              {{ formatNumber(row.failed) }}
            </TableCell>
          </TableRow>
          <TableRow v-if="!report && !summaryError">
            <TableCell :colspan="5">
              <Skeleton class="h-9" />
            </TableCell>
          </TableRow>
          <TableEmpty v-if="report && !report.connections.length" :colspan="5">
            Nothing sent in this period.
          </TableEmpty>
        </TableBody>
      </Table>
    </CardContent>
  </Card>

  <Card>
    <CardHeader>
      <CardTitle>Campaigns</CardTitle>
      <CardDescription>Started in the last {{ period }}, newest first</CardDescription>
    </CardHeader>
    <CardContent class="px-2 pt-2">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Campaign</TableHead>
            <TableHead class="hidden md:table-cell">
              Channel
            </TableHead>
            <TableHead class="hidden text-right lg:table-cell">
              Recipients
            </TableHead>
            <TableHead class="text-right">
              Delivered
            </TableHead>
            <TableHead class="hidden text-right sm:table-cell">
              Failed
            </TableHead>
            <TableHead class="hidden text-right md:table-cell">
              Delivery rate
            </TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow v-for="campaign in rows" :key="campaign.id">
            <TableCell>
              <RouterLink :to="{ name: 'campaigns.report', params: { campaign: campaign.id } }" class="font-medium">
                {{ campaign.name }}
              </RouterLink>
              <div class="text-xs text-muted-foreground">
                <span class="md:hidden">{{ campaign.channel === 'sms' ? 'SMS' : 'Email' }} · </span>{{ formatDateTime(campaign.started_at) }}{{ campaign.connection ? ` · ${campaign.connection.name}` : '' }}
              </div>
            </TableCell>
            <TableCell class="hidden md:table-cell">
              <ChannelLabel :channel="campaign.channel" />
            </TableCell>
            <TableCell class="hidden text-right tabular-nums lg:table-cell">
              {{ formatNumber(campaign.recipients_count) }}
            </TableCell>
            <TableCell class="text-right tabular-nums">
              {{ formatNumber(campaign.counts.delivered) }}
            </TableCell>
            <TableCell class="hidden text-right tabular-nums sm:table-cell">
              {{ formatNumber(campaign.counts.failed) }}
            </TableCell>
            <TableCell class="hidden text-right tabular-nums md:table-cell">
              {{ campaign.counts.sent ? `${campaign.delivery_rate}%` : '–' }}
            </TableCell>
          </TableRow>
          <TableRow v-if="rowsLoading && !rows.length">
            <TableCell :colspan="6">
              <Skeleton class="h-9" />
            </TableCell>
          </TableRow>
          <TableEmpty v-if="!rowsLoading && !rowsError && !rows.length" :colspan="6">
            <div class="grid place-items-center gap-3 py-6">
              <span class="grid size-11 place-items-center rounded-full bg-brand-soft text-brand"><BarChart3 class="size-5" /></span>
              <span>No campaigns started in the last {{ period }}.</span>
              <Button as-child size="sm">
                <RouterLink :to="{ name: 'campaigns.new' }">
                  <Plus />
                  New campaign
                </RouterLink>
              </Button>
            </div>
          </TableEmpty>
        </TableBody>
      </Table>
    </CardContent>
    <PaginationFooter v-model="page" :page-size="pageSize" :total="total" :page-count="pageCount" noun="campaigns" />
  </Card>
</template>
