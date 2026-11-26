<script setup lang="ts">
import { invalidateCache } from 'alova';
import { useWatcher } from 'alova/client';
import { computed, ref } from 'vue';
import { admin } from '@/api';
import UsageMeter from '@/components/admin/UsageMeter.vue';
import ChannelLabel from '@/components/app/ChannelLabel.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import RefreshButton from '@/components/app/RefreshButton.vue';
import StatCard from '@/components/app/StatCard.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableEmpty, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useProviderNames } from '@/composables/useProviderNames';
import { formatNumber } from '@/lib/format';

const providerName = useProviderNames();

const thisMonth = new Date().toISOString().slice(0, 7);
const month = ref(thisMonth);

const usageMethod = () => admin.usage(month.value || thisMonth);

const { data: usage, loading, error, send } = useWatcher(usageMethod, [month], { immediate: true });

async function refresh() {
  await invalidateCache(usageMethod());
  await send();
}

const kpis = computed(() => {
  const data = usage.value?.data;

  if (!data) {
    return [];
  }

  const rate = (sent: number, value: number) => sent ? `${value}%` : '–';

  return [
    { label: 'SMS sent', value: formatNumber(data.totals.sms), note: 'counted against plans' },
    { label: 'Emails sent', value: formatNumber(data.totals.emails), note: 'counted against plans' },
    { label: 'SMS delivery', value: rate(data.delivery.sms.sent, data.delivery.sms.delivery_rate), note: `${formatNumber(data.delivery.sms.failed)} failed` },
    { label: 'Email delivery', value: rate(data.delivery.email.sent, data.delivery.email.delivery_rate), note: `${formatNumber(data.delivery.email.failed)} failed` },
    { label: 'Over plan limit', value: formatNumber(data.over_limit), note: data.over_limit === 1 ? 'workspace' : 'workspaces' },
  ];
});

const busiestProvider = computed(() => Math.max(1, ...(usage.value?.data.providers ?? []).map(item => item.total)));

const monthLabel = computed(() => new Intl.DateTimeFormat('en-NG', { month: 'long', year: 'numeric' })
  .format(new Date(`${month.value || thisMonth}-01T00:00:00`)));
</script>

<template>
  <PageHeader title="Usage" :description="`Messages sent through the platform in ${monthLabel}, by workspace and provider.`">
    <template #actions>
      <Input v-model="month" type="month" :max="thisMonth" class="w-[170px]" aria-label="Month" />
      <RefreshButton :refresh="refresh" />
    </template>
  </PageHeader>

  <Alert v-if="error" variant="destructive" class="flex items-center justify-between gap-3">
    <AlertDescription>We couldn't load usage. {{ error.message }}</AlertDescription>
    <Button variant="secondary" size="sm" class="shrink-0" @click="refresh">
      Try again
    </Button>
  </Alert>

  <div class="grid grid-cols-[repeat(auto-fit,minmax(170px,1fr))] gap-4">
    <template v-if="kpis.length">
      <StatCard v-for="kpi in kpis" :key="kpi.label" v-bind="kpi" />
    </template>
    <Skeleton v-for="n in (kpis.length || error ? 0 : 5)" v-else :key="n" class="h-[110px] rounded-xl" />
  </div>

  <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
    <Card>
      <CardHeader>
        <CardTitle>Top workspaces by volume</CardTitle>
        <CardDescription>Against the allowance of the plan each is on now</CardDescription>
      </CardHeader>
      <CardContent class="pt-2">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Workspace</TableHead>
              <TableHead class="hidden sm:table-cell">
                Plan
              </TableHead>
              <TableHead class="text-right">
                Messages
              </TableHead>
              <TableHead class="hidden md:table-cell">
                Of allowance
              </TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <template v-if="usage">
              <TableRow v-for="item in usage.data.top" :key="item.id">
                <TableCell>
                  <RouterLink :to="{ name: 'admin.customers', query: { workspace: item.id } }" class="font-medium">
                    {{ item.name }}
                  </RouterLink>
                  <div class="text-xs text-muted-foreground tabular-nums">
                    {{ formatNumber(item.sms.used) }} SMS · {{ formatNumber(item.emails.used) }} email
                  </div>
                </TableCell>
                <TableCell class="hidden text-[13px] sm:table-cell">
                  {{ item.plan ?? 'No plan' }}
                </TableCell>
                <TableCell class="text-right tabular-nums">
                  {{ formatNumber(item.emails.used + item.sms.used) }}
                </TableCell>
                <TableCell class="hidden md:table-cell">
                  <UsageMeter :workspace="item" />
                </TableCell>
              </TableRow>
            </template>
            <template v-else-if="loading">
              <TableRow v-for="n in 4" :key="n">
                <TableCell :colspan="4">
                  <Skeleton class="h-9" />
                </TableCell>
              </TableRow>
            </template>
            <TableEmpty v-if="usage && !usage.data.top.length" :colspan="4">
              No messages sent in {{ monthLabel }}.
            </TableEmpty>
          </TableBody>
        </Table>
      </CardContent>
    </Card>

    <Card>
      <CardHeader>
        <CardTitle>By provider</CardTitle>
        <CardDescription>Messages handed to each provider</CardDescription>
      </CardHeader>
      <CardContent class="grid gap-3">
        <template v-if="usage">
          <div
            v-for="item in usage.data.providers"
            :key="`${item.provider}-${item.channel}`"
            class="grid grid-cols-[minmax(0,130px)_minmax(0,1fr)_64px] items-center gap-3 text-[13px]"
          >
            <span class="grid min-w-0">
              <span class="truncate">{{ providerName(item.provider) }}</span>
              <ChannelLabel :channel="item.channel" class="text-xs" />
            </span>
            <div class="h-2 overflow-hidden rounded-full bg-muted">
              <div class="h-full rounded-full" :class="item.channel === 'sms' ? 'bg-brand-grey' : 'bg-brand'" :style="{ width: `${item.total / busiestProvider * 100}%` }" />
            </div>
            <span class="text-right tabular-nums">{{ formatNumber(item.total) }}</span>
          </div>
          <p v-if="!usage.data.providers.length" class="text-sm text-muted-foreground">
            No messages sent in {{ monthLabel }}.
          </p>
        </template>
        <Skeleton v-for="n in (usage || error ? 0 : 3)" v-else :key="n" class="h-6" />
      </CardContent>
    </Card>
  </div>
</template>
