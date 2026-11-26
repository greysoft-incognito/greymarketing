<script setup lang="ts">
import type { Plan } from '@/api';
import { Check } from 'lucide-vue-next';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { formatMoney, formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';

const props = defineProps<{
  plan: Plan;
  current: Plan | null;
  canManage: boolean;
  busy?: boolean;
}>();

const emit = defineEmits<{
  choose: [];
}>();

const isCurrent = computed(() => props.plan.id === props.current?.id);
const isUpgrade = computed(() => !props.current || props.plan.price > props.current.price);

const action = computed(() => {
  if (isCurrent.value) {
    return 'Current plan';
  }

  if (!props.current) {
    return `Choose ${props.plan.name}`;
  }

  return isUpgrade.value ? `Upgrade to ${props.plan.name}` : `Switch to ${props.plan.name}`;
});

const limit = (value: number | null, noun: string) => value === null ? `Unlimited ${noun}` : `${formatNumber(value)} ${noun}`;

const features = computed(() => [
  limit(props.plan.limits.contacts, 'contacts'),
  `${limit(props.plan.limits.emails, 'emails')} a month`,
  `${limit(props.plan.limits.sms, 'SMS')} a month`,
]);
</script>

<template>
  <Card :class="cn('flex flex-col gap-4 p-[22px]', isCurrent && 'bg-brand-soft')">
    <div class="flex items-center justify-between gap-2">
      <span class="font-semibold">{{ plan.name }}</span>
      <Badge v-if="isCurrent" variant="solid">
        Current
      </Badge>
    </div>
    <div>
      <span class="text-[28px] font-semibold tracking-tight tabular-nums">{{ formatMoney(plan.price, plan.currency) }}</span>
      <span v-if="!plan.is_free" class="text-xs text-muted-foreground"> / {{ plan.interval }}</span>
    </div>
    <p v-if="plan.description" class="text-[13px] text-muted-foreground">
      {{ plan.description }}
    </p>
    <ul class="grid gap-2 text-[13px]">
      <li v-for="feature in features" :key="feature" class="flex items-center gap-2">
        <Check class="size-3.5 shrink-0 text-brand" />
        {{ feature }}
      </li>
    </ul>
    <Button
      v-if="canManage"
      class="mt-auto w-full"
      :variant="isCurrent || !isUpgrade ? 'secondary' : 'default'"
      :disabled="isCurrent || busy"
      @click="emit('choose')"
    >
      {{ action }}
    </Button>
  </Card>
</template>
