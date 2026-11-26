<script setup lang="ts">
import type { UsageItem } from '@/api';
import { computed } from 'vue';
import { Card } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';

const props = defineProps<{
  label: string;
  usage: UsageItem;
}>();

const percent = computed(() => props.usage.limit ? Math.round(props.usage.used / props.usage.limit * 100) : null);
</script>

<template>
  <Card class="grid gap-2 px-5 py-[18px]">
    <div class="flex items-center justify-between text-[13px] text-muted-foreground">
      {{ label }}
      <span v-if="percent !== null" :class="cn('text-xs tabular-nums', percent >= 100 && 'font-medium text-destructive')">{{ percent }}%</span>
    </div>
    <div class="flex items-baseline gap-1.5">
      <span class="text-[26px] font-semibold tracking-tight tabular-nums">{{ formatNumber(usage.used) }}</span>
      <span class="text-muted-foreground tabular-nums">{{ usage.limit === null ? 'of unlimited' : `of ${formatNumber(usage.limit)}` }}</span>
    </div>
    <Progress :model-value="percent ?? 0" :class="cn(percent !== null && percent >= 100 && '[&>[data-slot=progress-indicator]]:bg-destructive')" />
  </Card>
</template>
