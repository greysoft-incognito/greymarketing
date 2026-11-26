<script setup lang="ts">
import type { AdminWorkspace } from '@/api';
import { usePagination, useRequest } from 'alova/client';
import { ChevronRight, Search, Users } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { admin, forget } from '@/api';
import WorkspaceSheet from '@/components/admin/WorkspaceSheet.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import PaginationFooter from '@/components/app/PaginationFooter.vue';
import RefreshButton from '@/components/app/RefreshButton.vue';
import SegmentedTabs from '@/components/app/SegmentedTabs.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Avatar } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableEmpty, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formatNumber } from '@/lib/format';

const route = useRoute();
const router = useRouter();

const search = ref('');
const plan = ref('all');

const { data: planData } = useRequest(() => admin.plans({ limit: 100 }));

const plans = computed(() => planData.value?.data ?? []);
const planTabs = computed(() => [
  { value: 'all', label: 'All' },
  ...plans.value.filter(item => item.active_subscriptions_count).map(item => ({ value: item.id, label: item.name })),
]);

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
  (pageNumber, perPage) => admin.workspaces({
    page: pageNumber,
    limit: perPage,
    ...(search.value.trim() ? { search: search.value.trim() } : {}),
    ...(plan.value === 'all' ? {} : { plan: plan.value }),
  }),
  {
    watchingStates: [search, plan],
    debounce: [300, 0],
    initialPageSize: 30,
    total: response => response.meta.total,
    data: response => response.data,
  },
);

async function refresh() {
  await forget('admin.workspaces.list');
  await refreshRows();
}

const empty = computed(() => !loading.value && !error.value && !rows.value.length);

const selected = computed(() => typeof route.query.workspace === 'string' ? route.query.workspace : null);
const sheetOpen = ref(!!selected.value);

watch(selected, (value) => {
  sheetOpen.value = !!value;
});

watch(sheetOpen, (value) => {
  if (!value && selected.value) {
    void router.replace({ query: { ...route.query, workspace: undefined } });
  }
});

function open(workspace: AdminWorkspace) {
  void router.replace({ query: { ...route.query, workspace: workspace.id } });
}

function messages(workspace: AdminWorkspace) {
  const usage = workspace.usage_this_month;

  return usage ? formatNumber(usage.emails + usage.sms) : '–';
}

const joined = new Intl.DateTimeFormat('en-NG', { month: 'short', year: 'numeric' });

const statusVariant = {
  active: 'success',
  cancelled: 'warning',
  expired: 'default',
  pending: 'info',
} as const;
</script>

<template>
  <PageHeader title="Customers" description="Every workspace on the platform.">
    <template #actions>
      <RefreshButton :refresh="refresh" />
    </template>
  </PageHeader>

  <div class="flex flex-wrap items-center justify-between gap-3">
    <SegmentedTabs v-model="plan" :options="planTabs" label="Filter by plan" />
    <div class="relative w-full sm:w-[260px]">
      <Search class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
      <Input v-model="search" type="search" placeholder="Workspace or owner email" class="pl-9" aria-label="Search workspaces" />
    </div>
  </div>

  <Alert v-if="error" variant="destructive" class="flex items-center justify-between gap-3">
    <AlertDescription>We couldn't load workspaces. {{ error.message }}</AlertDescription>
    <Button variant="secondary" size="sm" class="shrink-0" @click="refresh">
      Try again
    </Button>
  </Alert>

  <Card class="p-2">
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead>Workspace</TableHead>
          <TableHead class="hidden lg:table-cell">
            Owner
          </TableHead>
          <TableHead class="hidden sm:table-cell">
            Plan
          </TableHead>
          <TableHead class="hidden text-right md:table-cell">
            Messages this month
          </TableHead>
          <TableHead class="hidden text-right md:table-cell">
            Contacts
          </TableHead>
          <TableHead class="hidden lg:table-cell">
            Joined
          </TableHead>
          <TableHead class="w-12" />
        </TableRow>
      </TableHeader>
      <TableBody>
        <template v-if="rows.length">
          <TableRow v-for="workspace in rows" :key="workspace.id" class="cursor-pointer" @click="open(workspace)">
            <TableCell>
              <div class="flex items-center gap-2.5">
                <Avatar :name="workspace.name" class="size-7 text-[11px]" />
                <div class="min-w-0">
                  <div class="max-w-[220px] truncate font-medium">
                    {{ workspace.name }}
                  </div>
                  <div class="max-w-[220px] truncate text-xs text-muted-foreground lg:hidden">
                    {{ workspace.owner?.email }}
                  </div>
                </div>
              </div>
            </TableCell>
            <TableCell class="hidden lg:table-cell">
              <div class="text-[13px]">
                {{ workspace.owner?.name }}
              </div>
              <div class="max-w-[220px] truncate text-xs text-muted-foreground">
                {{ workspace.owner?.email }}
              </div>
            </TableCell>
            <TableCell class="hidden sm:table-cell">
              <div class="flex items-center gap-2 text-[13px]">
                {{ workspace.subscription?.plan?.name ?? 'Default' }}
                <Badge v-if="workspace.subscription && workspace.subscription.status !== 'active'" :variant="statusVariant[workspace.subscription.status]" class="capitalize">
                  {{ workspace.subscription.status }}
                </Badge>
              </div>
            </TableCell>
            <TableCell class="hidden text-right tabular-nums md:table-cell">
              {{ messages(workspace) }}
            </TableCell>
            <TableCell class="hidden text-right tabular-nums md:table-cell">
              {{ formatNumber(workspace.contacts_count ?? 0) }}
            </TableCell>
            <TableCell class="hidden text-[13px] whitespace-nowrap text-muted-foreground lg:table-cell">
              {{ joined.format(new Date(workspace.created_at)) }}
            </TableCell>
            <TableCell class="text-right">
              <ChevronRight class="ml-auto size-4 text-muted-foreground" />
            </TableCell>
          </TableRow>
        </template>
        <template v-else-if="loading">
          <TableRow v-for="n in 5" :key="n">
            <TableCell :colspan="7">
              <Skeleton class="h-9" />
            </TableCell>
          </TableRow>
        </template>
        <TableEmpty v-if="empty" :colspan="7">
          <div class="grid place-items-center gap-3 py-6">
            <span class="grid size-11 place-items-center rounded-full bg-brand-soft text-brand"><Users class="size-5" /></span>
            <span>{{ search.trim() ? 'No workspaces match your search.' : 'No workspaces here.' }}</span>
          </div>
        </TableEmpty>
      </TableBody>
    </Table>
    <PaginationFooter v-model="page" :page-size="pageSize" :total="total" :page-count="pageCount" noun="workspaces" />
  </Card>

  <WorkspaceSheet
    v-if="selected"
    :key="selected"
    v-model:open="sheetOpen"
    :workspace-id="selected"
    :plans="plans"
    @changed="refresh"
  />
</template>
