<script setup lang="ts">
import type { AdminPlan, AdminSubscription, ApiError, UsageItem } from '@/api';
import { invalidateCache } from 'alova';
import { useRequest } from 'alova/client';
import { Loader2, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { admin } from '@/api';
import ConfirmDialog from '@/components/app/ConfirmDialog.vue';
import FormField from '@/components/app/FormField.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Progress } from '@/components/ui/progress';
import { Sheet, SheetClose, SheetContent, SheetDescription, SheetTitle } from '@/components/ui/sheet';
import { Skeleton } from '@/components/ui/skeleton';
import { formatDateTime, formatMoney, formatNumber } from '@/lib/format';
import { notifyError, toast } from '@/lib/notify';
import { cn } from '@/lib/utils';

const props = defineProps<{
  workspaceId: string;
  plans: AdminPlan[];
}>();

const emit = defineEmits<{ changed: [] }>();

const open = defineModel<boolean>('open', { default: false });

const billingMethod = () => admin.workspace(props.workspaceId);
const historyMethod = () => admin.subscriptions({ workspace: props.workspaceId, limit: 10 });

const { data: billing, error: billingError, send: sendBilling } = useRequest(billingMethod);
const { data: history, send: sendHistory } = useRequest(historyMethod);

async function reload() {
  await invalidateCache([billingMethod(), historyMethod()]);
  await Promise.all([sendBilling(), sendHistory()]);
  emit('changed');
}

const usage = computed(() => {
  const data = billing.value?.data.usage;

  if (!data) {
    return [];
  }

  return [
    { label: 'Contacts', ...data.contacts },
    { label: 'Emails this month', ...data.emails },
    { label: 'SMS this month', ...data.sms },
  ];
});

function share(item: UsageItem) {
  return item.limit ? Math.min(100, Math.round(item.used / item.limit * 100)) : 0;
}

const planId = ref('');
const endsAt = ref('');
const granting = ref(false);
const grantError = ref<ApiError | null>(null);

async function grant() {
  granting.value = true;
  grantError.value = null;

  try {
    const result = await admin.grant(props.workspaceId, {
      plan: planId.value,
      ends_at: endsAt.value ? new Date(`${endsAt.value}T23:59:59`).toISOString() : null,
    });

    toast.success(result.message);
    planId.value = '';
    endsAt.value = '';
    await reload();
  } catch (e) {
    grantError.value = notifyError(e);
  } finally {
    granting.value = false;
  }
}

const cancelling = ref<AdminSubscription | null>(null);
const confirmOpen = ref(false);

function askCancel(subscription: AdminSubscription) {
  cancelling.value = subscription;
  confirmOpen.value = true;
}

async function cancel() {
  try {
    await admin.cancelSubscription(cancelling.value!.id);
    toast.success('Subscription cancelled.');
    await reload();
  } catch (e) {
    notifyError(e);
  }
}

const statusVariant = {
  active: 'success',
  cancelled: 'warning',
  expired: 'default',
  pending: 'info',
} as const;
</script>

<template>
  <Sheet v-model:open="open">
    <SheetContent side="right" class="w-full max-w-[440px] overflow-y-auto bg-background p-0 sm:w-[440px]" @open-auto-focus.prevent>
      <div class="flex items-start gap-3 p-5 pb-3">
        <div class="grid min-w-0 flex-1 gap-1">
          <SheetTitle class="truncate text-base font-semibold">
            {{ billing?.data.workspace.name ?? 'Workspace' }}
          </SheetTitle>
          <SheetDescription class="text-sm text-muted-foreground">
            Plan, usage and subscriptions. Provider keys stay private to the workspace.
          </SheetDescription>
        </div>
        <SheetClose as-child>
          <Button variant="ghost" size="icon" aria-label="Close">
            <X />
          </Button>
        </SheetClose>
      </div>

      <div class="grid gap-6 px-5 pb-6">
        <p v-if="billingError" class="text-sm text-destructive">
          We couldn't load this workspace. {{ billingError.message }}
        </p>

        <section v-if="billing" class="grid gap-3 rounded-xl bg-muted p-4">
          <div class="flex items-center justify-between gap-3">
            <div>
              <div class="text-xs text-muted-foreground">
                Plan
              </div>
              <div class="font-semibold">
                {{ billing.data.plan?.name ?? 'No plan' }}
              </div>
            </div>
            <Badge v-if="billing.data.subscription" :variant="statusVariant[billing.data.subscription.status]" class="capitalize">
              {{ billing.data.subscription.status }}
            </Badge>
            <Badge v-else>
              Default plan
            </Badge>
          </div>
          <div v-if="billing.data.subscription?.ends_at" class="text-xs text-muted-foreground">
            {{ billing.data.subscription.status === 'active' ? 'Renews' : 'Ends' }} {{ formatDateTime(billing.data.subscription.ends_at) }}
          </div>
          <div v-for="item in usage" :key="item.label" class="grid gap-1.5">
            <div class="flex justify-between text-xs">
              <span>{{ item.label }}</span>
              <span class="text-muted-foreground tabular-nums">
                {{ formatNumber(item.used) }}{{ item.limit === null ? '' : ` of ${formatNumber(item.limit)}` }}
              </span>
            </div>
            <Progress v-if="item.limit !== null" :model-value="share(item)" :class="cn('bg-background', item.used >= item.limit && '[&>*]:bg-destructive')" />
          </div>
        </section>
        <Skeleton v-else-if="!billingError" class="h-44 rounded-xl" />

        <form class="grid gap-3" @submit.prevent="grant">
          <h3 class="text-sm font-semibold">
            Put on a plan
          </h3>
          <p class="-mt-2 text-xs text-muted-foreground">
            Starts now without payment, for support or a trial. It replaces the current plan.
          </p>
          <FormField id="grant-plan" label="Plan" :error="grantError?.field('plan')">
            <NativeSelect id="grant-plan" v-model="planId">
              <option value="" disabled>
                Choose a plan
              </option>
              <option v-for="plan in plans" :key="plan.id" :value="plan.id">
                {{ plan.name }} · {{ plan.is_free ? 'Free' : `${formatMoney(plan.price, plan.currency)} / ${plan.interval}` }}
              </option>
            </NativeSelect>
          </FormField>
          <FormField id="grant-ends" label="Ends on" hint="Optional. Leave empty for one billing period." :error="grantError?.field('ends_at')">
            <Input id="grant-ends" v-model="endsAt" type="date" />
          </FormField>
          <Button type="submit" class="justify-self-start" :disabled="granting || !planId">
            <Loader2 v-if="granting" class="animate-spin" />
            Put on plan
          </Button>
        </form>

        <section class="grid gap-2">
          <h3 class="text-sm font-semibold">
            Subscriptions
          </h3>
          <div
            v-for="subscription in history?.data ?? []"
            :key="subscription.id"
            class="flex items-center gap-3 border-b border-border py-2 text-[13px] last:border-0"
          >
            <div class="grid min-w-0 flex-1 gap-0.5">
              <span class="font-medium">{{ subscription.plan?.name }} · {{ subscription.amount ? formatMoney(subscription.amount, subscription.currency) : 'Free' }}</span>
              <span class="text-xs text-muted-foreground">
                {{ formatDateTime(subscription.starts_at ?? subscription.created_at) }}{{ subscription.ends_at ? ` – ${formatDateTime(subscription.ends_at)}` : '' }}
              </span>
            </div>
            <Badge :variant="statusVariant[subscription.status]" class="capitalize">
              {{ subscription.status }}
            </Badge>
            <Button v-if="subscription.status === 'active'" variant="ghost" size="sm" @click="askCancel(subscription)">
              Cancel
            </Button>
          </div>
          <p v-if="history && !history.data.length" class="text-sm text-muted-foreground">
            No subscriptions yet.
          </p>
        </section>
      </div>
    </SheetContent>
  </Sheet>

  <ConfirmDialog
    v-model:open="confirmOpen"
    title="Cancel this subscription?"
    description="It stops renewing. The workspace keeps the plan until the period ends."
    action="Cancel subscription"
    :run="cancel"
  />
</template>
