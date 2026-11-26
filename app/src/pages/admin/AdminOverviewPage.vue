<script setup lang="ts">
import { invalidateCache } from 'alova';
import { useRequest } from 'alova/client';
import { ChevronRight, ShieldCheck } from 'lucide-vue-next';
import { computed } from 'vue';
import { admin } from '@/api';
import UsageMeter from '@/components/admin/UsageMeter.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import ProviderLogo from '@/components/app/ProviderLogo.vue';
import RefreshButton from '@/components/app/RefreshButton.vue';
import StatCard from '@/components/app/StatCard.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableEmpty, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useProviderNames } from '@/composables/useProviderNames';
import { formatMoney, formatNumber, percentChange } from '@/lib/format';

const providerName = useProviderNames();

const overviewMethod = () => admin.overview();

const { data: overview, error, send } = useRequest(overviewMethod);

async function refresh() {
  await invalidateCache(overviewMethod());
  await send();
}

const month = new Intl.DateTimeFormat('en-NG', { month: 'long', year: 'numeric' }).format(new Date());

const kpis = computed(() => {
  const data = overview.value?.data;

  if (!data) {
    return [];
  }

  const sent = data.messages.this_month.emails + data.messages.this_month.sms;
  const lastSent = data.messages.last_month.emails + data.messages.last_month.sms;
  const change = percentChange(sent, lastSent);

  return [
    {
      label: 'Workspaces',
      value: formatNumber(data.workspaces.total),
      delta: data.workspaces.new_this_month ? `+${formatNumber(data.workspaces.new_this_month)}` : null,
      trend: 'up',
      note: 'joined this month',
    },
    {
      label: 'Paying workspaces',
      value: formatNumber(data.workspaces.paying),
      delta: null,
      trend: 'flat',
      note: 'on a paid plan now',
    },
    {
      label: 'Monthly recurring revenue',
      value: data.mrr.length ? data.mrr.map(item => formatMoney(item.amount, item.currency)).join(' · ') : formatMoney(0, 'NGN'),
      delta: null,
      trend: 'flat',
      note: 'yearly plans counted per month',
    },
    {
      label: 'Messages this month',
      value: formatNumber(sent),
      delta: change === null ? null : `${change >= 0 ? '+' : '−'}${Math.abs(change)}%`,
      trend: change === null ? 'flat' : change >= 0 ? 'up' : 'down',
      note: change === null ? `${formatNumber(data.messages.this_month.sms)} SMS, ${formatNumber(data.messages.this_month.emails)} email` : 'vs last month',
    },
  ] as const;
});

const busiestPlan = computed(() => Math.max(1, ...(overview.value?.data.plans ?? []).map(plan => plan.workspaces)));

function healthVariant(rate: number) {
  if (rate >= 4) {
    return 'destructive';
  }

  return rate >= 2 ? 'warning' : 'success';
}
</script>

<template>
  <PageHeader title="Platform overview" :description="`All workspaces · ${month}`">
    <template #actions>
      <Badge variant="solid" class="h-9 rounded-md px-3 text-[13px]">
        <ShieldCheck />
        Admin
      </Badge>
      <RefreshButton :refresh="refresh" />
    </template>
  </PageHeader>

  <Alert v-if="error" variant="destructive" class="flex items-center justify-between gap-3">
    <AlertDescription>We couldn't load the platform figures. {{ error.message }}</AlertDescription>
    <Button variant="secondary" size="sm" class="shrink-0" @click="refresh">
      Try again
    </Button>
  </Alert>

  <div class="grid grid-cols-[repeat(auto-fit,minmax(200px,1fr))] gap-4">
    <template v-if="kpis.length">
      <StatCard v-for="kpi in kpis" :key="kpi.label" v-bind="kpi" />
    </template>
    <Skeleton v-for="n in (kpis.length || error ? 0 : 4)" v-else :key="n" class="h-[110px] rounded-xl" />
  </div>

  <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
    <Card>
      <CardHeader>
        <CardTitle>Workspaces by plan</CardTitle>
        <CardDescription>Workspaces without a subscription count toward the default plan.</CardDescription>
      </CardHeader>
      <CardContent class="grid gap-3">
        <template v-if="overview">
          <div
            v-for="plan in overview.data.plans"
            :key="plan.id"
            class="grid grid-cols-[110px_minmax(0,1fr)_56px] items-center gap-3 text-[13px]"
          >
            <span class="truncate">{{ plan.name }}</span>
            <div class="h-2 overflow-hidden rounded-full bg-muted">
              <div class="h-full rounded-full bg-brand" :style="{ width: `${plan.workspaces / busiestPlan * 100}%` }" />
            </div>
            <span class="text-right tabular-nums">{{ formatNumber(plan.workspaces) }}</span>
          </div>
          <p v-if="!overview.data.plans.length" class="text-sm text-muted-foreground">
            No plans yet.
          </p>
        </template>
        <Skeleton v-for="n in (overview || error ? 0 : 4)" v-else :key="n" class="h-5" />
      </CardContent>
    </Card>

    <Card>
      <CardHeader>
        <CardTitle>Provider health</CardTitle>
        <CardDescription>Failed sends across all workspaces, last 24 hours</CardDescription>
      </CardHeader>
      <CardContent class="grid gap-2.5">
        <template v-if="overview">
          <div v-for="item in overview.data.providers" :key="item.provider" class="flex items-center justify-between gap-3 text-[13px]">
            <span class="flex min-w-0 items-center gap-2.5">
              <ProviderLogo :provider="item.provider" :name="providerName(item.provider)" class="size-7 rounded-md text-[11px]" />
              <span class="truncate">{{ providerName(item.provider) }}</span>
              <span class="text-xs text-muted-foreground tabular-nums">{{ formatNumber(item.total) }} sent</span>
            </span>
            <Badge :variant="healthVariant(item.error_rate)" class="tabular-nums">
              {{ item.error_rate }}%
            </Badge>
          </div>
          <p v-if="!overview.data.providers.length" class="text-sm text-muted-foreground">
            Nothing was sent in the last 24 hours.
          </p>
        </template>
        <Skeleton v-for="n in (overview || error ? 0 : 3)" v-else :key="n" class="h-7" />
      </CardContent>
    </Card>
  </div>

  <Card>
    <CardHeader>
      <CardTitle>Busiest workspaces</CardTitle>
      <CardDescription>
        <template v-if="overview?.data.over_limit">
          {{ formatNumber(overview.data.over_limit) }} {{ overview.data.over_limit === 1 ? 'workspace has' : 'workspaces have' }} used up a monthly allowance.
        </template>
        <template v-else>
          Messages sent this month against each plan's allowance.
        </template>
      </CardDescription>
      <CardAction>
        <Button variant="ghost" size="sm" as-child>
          <RouterLink :to="{ name: 'admin.usage' }">
            Usage
            <ChevronRight />
          </RouterLink>
        </Button>
      </CardAction>
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
          <template v-if="overview">
            <TableRow v-for="item in overview.data.top" :key="item.id">
              <TableCell>
                <RouterLink :to="{ name: 'admin.customers', query: { workspace: item.id } }" class="font-medium">
                  {{ item.name }}
                </RouterLink>
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
          <TableEmpty v-if="overview && !overview.data.top.length" :colspan="4">
            No messages sent this month yet.
          </TableEmpty>
        </TableBody>
      </Table>
    </CardContent>
  </Card>
</template>
