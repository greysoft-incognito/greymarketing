<script setup lang="ts">
import { Card } from '@/components/ui/card';

defineProps<{
  label: string;
  value: string;
  delta?: string | null;
  trend?: 'up' | 'down' | 'flat';
  note?: string;
}>();
</script>

<template>
  <Card class="grid gap-1.5 px-5 py-[18px]">
    <div class="text-[13px] text-muted-foreground">
      {{ label }}
    </div>
    <div class="text-[26px] font-semibold tracking-tight tabular-nums">
      {{ value }}
    </div>
    <div class="text-xs font-medium">
      <span v-if="delta" :class="{ 'text-success': trend === 'up', 'text-destructive': trend === 'down' }">{{ delta }}</span>
      <span class="font-normal text-muted-foreground">{{ delta ? ' ' : '' }}{{ note }}</span>
    </div>
  </Card>
</template>
