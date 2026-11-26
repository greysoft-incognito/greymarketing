<script setup lang="ts">
import type { Plan } from '@/api';
import { invalidateCache } from 'alova';
import { usePagination, useRequest } from 'alova/client';
import { Download, Receipt } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { billing, forget, workspaces } from '@/api';
import ConfirmDialog from '@/components/app/ConfirmDialog.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import PaginationFooter from '@/components/app/PaginationFooter.vue';
import RefreshButton from '@/components/app/RefreshButton.vue';
import SegmentedTabs from '@/components/app/SegmentedTabs.vue';
import PlanCard from '@/components/billing/PlanCard.vue';
import UsageCard from '@/components/billing/UsageCard.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { Switch } from '@/components/ui/switch';
import { Table, TableBody, TableCell, TableEmpty, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { downloadBlob } from '@/lib/csv';
import { formatMoney } from '@/lib/format';
import { notifyError, toast } from '@/lib/notify';
import { useWorkspace } from '@/stores/workspace';

type Interval = Plan['interval'];

const workspace = useWorkspace();
const id = workspace.current!.id;
const route = useRoute();
const router = useRouter();

const summaryMethod = () => workspaces.billing(id);
const plansMethod = () => billing.plans();

const { data: summary, error: summaryError, send: sendSummary } = useRequest(summaryMethod);
const { data: planData, error: plansError, send: sendPlans } = useRequest(plansMethod);
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
  (pageNumber, perPage) => billing.transactions(id, { page: pageNumber, limit: perPage }),
  {
    watchingStates: [],
    immediate: workspace.canManage,
    initialPageSize: 10,
    total: response => response.meta.total,
    data: response => response.data,
  },
);

watch(summary, value => {
  if (value) {
    workspace.billing = value.data;
  }
});

const current = computed(() => summary.value?.data.plan ?? null);
const subscription = computed(() => summary.value?.data.subscription ?? null);
const usage = computed(() => summary.value?.data.usage);
const loadError = computed(() => summaryError.value ?? plansError.value);

const plans = computed(() => planData.value?.data ?? []);
const intervals = computed(() => [...new Set(plans.value.filter(plan => !plan.is_free).map(plan => plan.interval))]);
const interval = ref<Interval>('month');
const intervalOptions = computed(() => intervals.value.map(value => ({ value, label: value === 'month' ? 'Monthly' : 'Yearly' })));
const shownPlans = computed(() => plans.value.filter(plan => plan.is_free || intervals.value.length < 2 || plan.interval === interval.value));

watch(intervals, values => {
  if (values.length && !values.includes(interval.value)) {
    interval.value = values[0]!;
  }
});

const formatDate = (value: string | null) => value
  ? new Intl.DateTimeFormat('en-NG', { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(value))
  : '';

const price = (plan: Plan) => `${formatMoney(plan.price, plan.currency)} / ${plan.interval}`;

const status = computed(() => {
  const sub = subscription.value;

  if (!sub) {
    return current.value ? { label: 'Free', variant: 'default' as const, note: 'No payment needed' } : null;
  }

  if (sub.status === 'cancelled') {
    return { label: 'Cancelled', variant: 'warning' as const, note: `Ends ${formatDate(sub.ends_at)}` };
  }

  return { label: 'Active', variant: 'success' as const, note: sub.renews_at ? `Renews ${formatDate(sub.renews_at)}` : `Paid until ${formatDate(sub.ends_at)}` };
});

const paidPlan = computed(() => !!subscription.value && !!current.value && !current.value.is_free);

const card = computed(() => {
  const saved = subscription.value?.card;

  return saved ? `${saved.brand ?? 'Card'} •••• ${saved.last4 ?? ''}`.trim() : null;
});

const renewalNote = computed(() => {
  const sub = subscription.value!;

  if (!card.value) {
    return 'Pay by card once to renew automatically.';
  }

  return sub.auto_renew
    ? `We'll charge ${card.value} on ${formatDate(sub.ends_at)}.`
    : `Off. Your plan ends on ${formatDate(sub.ends_at)}.`;
});

const togglingRenewal = ref(false);

/**
 * Turn renewal with the saved card on or off. Turning it on also undoes a cancellation.
 */
async function setAutoRenew(enabled: boolean) {
  togglingRenewal.value = true;

  try {
    toast.success((await billing.autoRenew(id, enabled)).message);
    await sendSummary();
  } catch (e) {
    notifyError(e);
  } finally {
    togglingRenewal.value = false;
  }
}

const downloading = ref<string | null>(null);

async function downloadInvoice(reference: string, number: string) {
  downloading.value = reference;

  try {
    downloadBlob(`${number}.pdf`, await billing.invoice(id, reference));
  } catch (e) {
    notifyError(e);
  } finally {
    downloading.value = null;
  }
}

/**
 * Fetch the plan, usage, plans and payments again, skipping anything cached.
 */
async function refresh() {
  await Promise.all([invalidateCache(summaryMethod()), invalidateCache(plansMethod()), forget('billing.transactions')]);
  await Promise.all([sendSummary(), sendPlans(), workspace.canManage ? refreshRows() : null]);
}

const busy = ref(false);
const verifying = ref(false);

const returnUrl = () => new URL(router.resolve({ name: 'billing' }).href, window.location.origin).href;

/**
 * Start a subscription: free plans switch at once, paid plans continue on Flutterwave.
 */
async function subscribe(plan: Plan) {
  busy.value = true;

  try {
    const result = await billing.subscribe(id, plan.id, returnUrl());

    if (result.checkout_url) {
      window.location.assign(result.checkout_url);

      return;
    }

    toast.success(result.message);
    await sendSummary();
  } catch (e) {
    notifyError(e);
  }

  busy.value = false;
}

const confirm = ref<{ title: string; description: string; action: string; variant: 'default' | 'destructive'; run: () => Promise<unknown> } | null>(null);
const confirmOpen = ref(false);

function ask(options: NonNullable<typeof confirm.value>) {
  confirm.value = options;
  confirmOpen.value = true;
}

function choose(plan: Plan) {
  const from = paidPlan.value ? current.value : null;
  const upgrade = !current.value || plan.price > current.value.price;

  if (plan.is_free) {
    ask({
      title: `Switch to ${plan.name}?`,
      description: from ? `Your ${from.name} plan ends now, along with any days left on it.` : `${plan.name} starts right away.`,
      action: 'Switch plan',
      variant: from ? 'destructive' : 'default',
      run: () => subscribe(plan),
    });

    return;
  }

  ask({
    title: `${upgrade ? 'Upgrade' : 'Switch'} to ${plan.name}?`,
    description: `You'll pay ${price(plan)} on Flutterwave.${from ? ` Your ${from.name} plan ends once the payment goes through.` : ''} If you pay by card, we'll charge it each ${plan.interval} until you turn off auto-renew.`,
    action: 'Continue to payment',
    variant: 'default',
    run: () => subscribe(plan),
  });
}

function renew() {
  const plan = current.value!;

  ask({
    title: `Pay for another ${plan.interval}?`,
    description: `You'll pay ${price(plan)} on Flutterwave. The new ${plan.interval} starts when this one ends on ${formatDate(subscription.value!.ends_at)}.`,
    action: 'Continue to payment',
    variant: 'default',
    run: () => subscribe(plan),
  });
}

function cancel() {
  ask({
    title: `Cancel your ${current.value!.name} plan?`,
    description: `You keep its limits until ${formatDate(subscription.value!.ends_at)}. After that the workspace moves to the free plan${subscription.value!.auto_renew ? ' and your card is not charged again' : ''}.`,
    action: 'Cancel plan',
    variant: 'destructive',
    run: async () => {
      try {
        toast.success((await billing.cancel(id)).message);
        await sendSummary();
      } catch (e) {
        notifyError(e);
      }
    },
  });
}

/**
 * Confirm the payment Flutterwave redirected back with, then drop its query from the address bar.
 */
async function confirmReturn() {
  const { tx_ref: reference, status: result } = route.query;

  if (typeof reference !== 'string') {
    return;
  }

  await router.replace({ query: {} });

  if (result === 'cancelled') {
    toast.info('Payment cancelled. Your plan has not changed.');

    return;
  }

  verifying.value = true;

  try {
    toast.success((await billing.verify(id, reference)).message);
    await sendSummary();
  } catch (e) {
    notifyError(e);
  } finally {
    verifying.value = false;
  }
}

onMounted(confirmReturn);
</script>

<template>
  <PageHeader title="Billing" description="Your GreyMarketing plan. SMS and email sends are billed by your own providers.">
    <template #actions>
      <RefreshButton :refresh="refresh" />
    </template>
  </PageHeader>

  <Alert v-if="verifying" variant="info">
    <AlertDescription>Confirming your payment with Flutterwave…</AlertDescription>
  </Alert>

  <Alert v-if="loadError" variant="destructive" class="flex items-center justify-between gap-3">
    <AlertDescription>We couldn't load your billing details. {{ loadError.message }}</AlertDescription>
    <Button variant="secondary" size="sm" class="shrink-0" @click="refresh">
      Try again
    </Button>
  </Alert>

  <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
    <template v-if="usage">
      <UsageCard label="Emails this month" :usage="usage.emails" />
      <UsageCard label="SMS this month" :usage="usage.sms" />
      <UsageCard label="Contacts" :usage="usage.contacts" />
    </template>
    <Skeleton v-for="n in (summaryError ? 0 : 3)" v-else :key="n" class="h-[106px] rounded-xl" />
  </div>

  <Card v-if="summary" class="flex-row flex-wrap items-center justify-between gap-4 px-5 py-[18px]">
    <div class="grid gap-1">
      <div class="flex items-center gap-2">
        <span class="font-semibold">{{ current ? `${current.name} plan` : 'No plan' }}</span>
        <Badge v-if="status" :variant="status.variant">
          {{ status.label }}
        </Badge>
      </div>
      <span class="text-[13px] text-muted-foreground">
        <template v-if="current">
          {{ current.is_free ? 'Free' : price(current) }} · {{ status?.note }}{{ paidPlan ? ' · paid through Flutterwave' : '' }}
        </template>
        <template v-else>Choose a plan below to start sending.</template>
      </span>
      <div v-if="paidPlan" class="mt-2 flex items-center gap-2.5">
        <Switch
          v-if="workspace.canManage"
          :model-value="subscription!.auto_renew"
          :disabled="togglingRenewal || !card"
          aria-label="Renew automatically"
          @update:model-value="setAutoRenew($event)"
        />
        <span class="text-[13px]">
          <span class="font-medium">Auto-renew</span>
          <span class="text-muted-foreground"> · {{ renewalNote }}</span>
        </span>
      </div>
    </div>
    <div v-if="workspace.canManage && paidPlan" class="flex flex-wrap gap-2">
      <Button variant="secondary" :disabled="busy" @click="renew">
        Pay for another {{ current!.interval }}
      </Button>
      <Button v-if="subscription!.status === 'active'" variant="ghost" :disabled="busy" @click="cancel">
        Cancel plan
      </Button>
    </div>
  </Card>
  <Skeleton v-else-if="!summaryError" class="h-[78px] rounded-xl" />

  <div class="flex flex-wrap items-center justify-between gap-3">
    <h2 class="text-base font-semibold">
      Change plan
    </h2>
    <SegmentedTabs v-if="intervalOptions.length > 1" v-model="interval" :options="intervalOptions" label="Billing period" />
  </div>

  <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <template v-if="planData && summary">
      <PlanCard
        v-for="plan in shownPlans"
        :key="plan.id"
        :plan="plan"
        :current="current"
        :can-manage="workspace.canManage"
        :busy="busy"
        @choose="choose(plan)"
      />
    </template>
    <Skeleton v-for="n in (loadError ? 0 : 3)" v-else :key="n" class="h-[260px] rounded-xl" />
  </div>

  <p v-if="!workspace.canManage" class="text-sm text-muted-foreground">
    Only workspace owners and admins can change the plan or see payments.
  </p>

  <Card v-if="workspace.canManage">
    <CardHeader>
      <CardTitle>Payments</CardTitle>
    </CardHeader>
    <CardContent class="px-2 pt-2 pb-0">
      <Alert v-if="rowsError" variant="destructive" class="mx-3 mb-2 flex items-center justify-between gap-3">
        <AlertDescription>We couldn't load your payments. {{ rowsError.message }}</AlertDescription>
        <Button variant="secondary" size="sm" class="shrink-0" @click="refreshRows()">
          Try again
        </Button>
      </Alert>
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Reference</TableHead>
            <TableHead class="hidden sm:table-cell">
              Date
            </TableHead>
            <TableHead class="hidden md:table-cell">
              Plan
            </TableHead>
            <TableHead class="text-right">
              Amount
            </TableHead>
            <TableHead>Status</TableHead>
            <TableHead class="w-10">
              <span class="sr-only">Invoice</span>
            </TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <template v-if="rows.length">
            <TableRow v-for="payment in rows" :key="payment.reference">
              <TableCell>
                <div class="max-w-[150px] truncate font-mono text-xs sm:max-w-none">
                  {{ payment.reference }}
                </div>
                <div class="text-xs text-muted-foreground sm:hidden">
                  {{ formatDate(payment.created_at) }}
                </div>
              </TableCell>
              <TableCell class="hidden text-[13px] whitespace-nowrap sm:table-cell">
                {{ formatDate(payment.created_at) }}
              </TableCell>
              <TableCell class="hidden text-[13px] md:table-cell">
                {{ payment.subscription ? `${payment.subscription.plan.name} · ${payment.subscription.plan.interval === 'month' ? 'monthly' : 'yearly'}` : '–' }}
              </TableCell>
              <TableCell class="text-right tabular-nums">
                {{ formatMoney(payment.due, payment.subscription?.currency ?? 'NGN') }}
              </TableCell>
              <TableCell>
                <Badge v-if="payment.status === 'complete'" variant="success">
                  Paid
                </Badge>
                <Badge v-else-if="payment.status === 'pending'" variant="warning">
                  Pending
                </Badge>
                <Badge v-else variant="destructive">
                  Failed
                </Badge>
              </TableCell>
              <TableCell class="text-right">
                <Button
                  v-if="payment.invoice_number"
                  variant="ghost"
                  size="icon"
                  class="size-8"
                  :disabled="downloading === payment.reference"
                  :aria-label="`Download invoice ${payment.invoice_number}`"
                  :title="`Download invoice ${payment.invoice_number}`"
                  @click="downloadInvoice(payment.reference, payment.invoice_number)"
                >
                  <Download />
                </Button>
              </TableCell>
            </TableRow>
          </template>
          <template v-else-if="rowsLoading">
            <TableRow v-for="n in 3" :key="n">
              <TableCell :colspan="6">
                <Skeleton class="h-8" />
              </TableCell>
            </TableRow>
          </template>
          <TableEmpty v-if="!rows.length && !rowsLoading && !rowsError" :colspan="6">
            <div class="grid place-items-center gap-3 py-6">
              <span class="grid size-11 place-items-center rounded-full bg-brand-soft text-brand"><Receipt class="size-5" /></span>
              <span>No payments yet.</span>
            </div>
          </TableEmpty>
        </TableBody>
      </Table>
      <PaginationFooter v-model="page" :page-size="pageSize" :total="total" :page-count="pageCount" noun="payments" />
    </CardContent>
  </Card>

  <ConfirmDialog
    v-if="confirm"
    v-model:open="confirmOpen"
    :title="confirm.title"
    :description="confirm.description"
    :action="confirm.action"
    :variant="confirm.variant"
    :run="confirm.run"
  />
</template>
