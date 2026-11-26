<script setup lang="ts">
import type { AdminPlan } from '@/api';
import { usePagination } from 'alova/client';
import { Layers, MoreHorizontal, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { admin, forget } from '@/api';
import PlanDialog from '@/components/admin/PlanDialog.vue';
import ConfirmDialog from '@/components/app/ConfirmDialog.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import PaginationFooter from '@/components/app/PaginationFooter.vue';
import RefreshButton from '@/components/app/RefreshButton.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Skeleton } from '@/components/ui/skeleton';
import { Switch } from '@/components/ui/switch';
import { Table, TableBody, TableCell, TableEmpty, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formatMoney, formatNumber } from '@/lib/format';
import { notifyError, toast } from '@/lib/notify';

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
  (pageNumber, perPage) => admin.plans({ page: pageNumber, limit: perPage }),
  {
    watchingStates: [],
    initialPageSize: 30,
    total: response => response.meta.total,
    data: response => response.data,
  },
);

async function refresh() {
  await forget('admin.plans.list');
  await refreshRows();
}

const empty = computed(() => !loading.value && !error.value && !rows.value.length);

const editing = ref<AdminPlan | null>(null);
const dialogOpen = ref(false);

function edit(plan: AdminPlan | null) {
  editing.value = plan;
  dialogOpen.value = true;
}

const toggling = ref<string | null>(null);

async function toggle(plan: AdminPlan, active: boolean) {
  toggling.value = plan.id;

  try {
    await admin.updatePlan(plan.id, { is_active: active });
    toast.success(active ? `${plan.name} is open to customers.` : `${plan.name} is hidden from customers.`);
    await refresh();
  } catch (e) {
    notifyError(e);
  } finally {
    toggling.value = null;
  }
}

const removing = ref<AdminPlan | null>(null);
const confirmOpen = ref(false);

function askRemove(plan: AdminPlan) {
  removing.value = plan;
  confirmOpen.value = true;
}

async function remove() {
  try {
    await admin.removePlan(removing.value!.id);
    toast.success(`${removing.value!.name} deleted.`);
    await refresh();
  } catch (e) {
    notifyError(e);
  }
}

const price = (plan: AdminPlan) => plan.is_free ? 'Free' : formatMoney(plan.price, plan.currency);
const limit = (value: number | null) => value === null ? 'No limit' : formatNumber(value);
</script>

<template>
  <PageHeader title="Plans" description="What customers can buy and the limits each plan enforces.">
    <template #actions>
      <RefreshButton :refresh="refresh" />
      <Button @click="edit(null)">
        <Plus />
        New plan
      </Button>
    </template>
  </PageHeader>

  <Alert v-if="error" variant="destructive" class="flex items-center justify-between gap-3">
    <AlertDescription>We couldn't load plans. {{ error.message }}</AlertDescription>
    <Button variant="secondary" size="sm" class="shrink-0" @click="refresh">
      Try again
    </Button>
  </Alert>

  <Card class="p-2">
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead>Plan</TableHead>
          <TableHead class="text-right">
            Price
          </TableHead>
          <TableHead class="hidden text-right md:table-cell">
            Contacts
          </TableHead>
          <TableHead class="hidden text-right md:table-cell">
            Emails / mo
          </TableHead>
          <TableHead class="hidden text-right md:table-cell">
            SMS / mo
          </TableHead>
          <TableHead class="hidden text-right lg:table-cell">
            Seats
          </TableHead>
          <TableHead class="hidden text-right sm:table-cell">
            Subscribers
          </TableHead>
          <TableHead>Open</TableHead>
          <TableHead class="w-12" />
        </TableRow>
      </TableHeader>
      <TableBody>
        <template v-if="rows.length">
          <TableRow v-for="plan in rows" :key="plan.id">
            <TableCell>
              <div class="flex items-center gap-2">
                <button type="button" class="cursor-pointer font-medium" @click="edit(plan)">
                  {{ plan.name }}
                </button>
                <Badge v-if="plan.is_default" variant="brand">
                  Default
                </Badge>
              </div>
              <div class="text-xs text-muted-foreground">
                {{ plan.slug }}
              </div>
            </TableCell>
            <TableCell class="text-right whitespace-nowrap tabular-nums">
              {{ price(plan) }}<span v-if="!plan.is_free" class="text-xs text-muted-foreground"> / {{ plan.interval }}</span>
            </TableCell>
            <TableCell class="hidden text-right tabular-nums md:table-cell">
              {{ limit(plan.limits.contacts) }}
            </TableCell>
            <TableCell class="hidden text-right tabular-nums md:table-cell">
              {{ limit(plan.limits.emails) }}
            </TableCell>
            <TableCell class="hidden text-right tabular-nums md:table-cell">
              {{ limit(plan.limits.sms) }}
            </TableCell>
            <TableCell class="hidden text-right tabular-nums lg:table-cell">
              {{ limit(plan.limits.seats) }}
            </TableCell>
            <TableCell class="hidden text-right tabular-nums sm:table-cell">
              {{ formatNumber(plan.active_subscriptions_count ?? 0) }}
            </TableCell>
            <TableCell>
              <Switch
                :model-value="plan.is_active"
                :disabled="toggling === plan.id"
                :aria-label="`Customers can choose ${plan.name}`"
                @update:model-value="toggle(plan, $event)"
              />
            </TableCell>
            <TableCell class="text-right">
              <DropdownMenu>
                <DropdownMenuTrigger as-child>
                  <Button variant="ghost" size="icon" class="size-8" :aria-label="`More for ${plan.name}`">
                    <MoreHorizontal />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                  <DropdownMenuItem @select="edit(plan)">
                    <Pencil />
                    Edit
                  </DropdownMenuItem>
                  <template v-if="!plan.active_subscriptions_count">
                    <DropdownMenuSeparator />
                    <DropdownMenuItem variant="destructive" @select="askRemove(plan)">
                      <Trash2 />
                      Delete
                    </DropdownMenuItem>
                  </template>
                </DropdownMenuContent>
              </DropdownMenu>
            </TableCell>
          </TableRow>
        </template>
        <template v-else-if="loading">
          <TableRow v-for="n in 4" :key="n">
            <TableCell :colspan="9">
              <Skeleton class="h-9" />
            </TableCell>
          </TableRow>
        </template>
        <TableEmpty v-if="empty" :colspan="9">
          <div class="grid place-items-center gap-3 py-6">
            <span class="grid size-11 place-items-center rounded-full bg-brand-soft text-brand"><Layers class="size-5" /></span>
            <span>No plans yet.</span>
            <Button size="sm" @click="edit(null)">
              <Plus />
              New plan
            </Button>
          </div>
        </TableEmpty>
      </TableBody>
    </Table>
    <PaginationFooter v-model="page" :page-size="pageSize" :total="total" :page-count="pageCount" noun="plans" />
  </Card>

  <PlanDialog v-model:open="dialogOpen" :plan="editing" @saved="refresh" />

  <ConfirmDialog
    v-if="removing"
    v-model:open="confirmOpen"
    :title="`Delete ${removing.name}?`"
    description="Customers can no longer choose it. Plans that ever had a subscription can only be closed, not deleted."
    action="Delete plan"
    :run="remove"
  />
</template>
