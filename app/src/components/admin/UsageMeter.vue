<script setup lang="ts">
import type { TopWorkspace } from '@/api';
import { computed } from 'vue';
import { Progress } from '@/components/ui/progress';
import { cn } from '@/lib/utils';

const props = defineProps<{ workspace: TopWorkspace }>();

/**
 * The fuller of the email and SMS allowances, since either one stops sending when it runs out.
 */
const percent = computed(() => {
  const shares = [props.workspace.emails, props.workspace.sms]
    .filter(item => item.limit)
    .map(item => item.used / item.limit! * 100);

  return shares.length ? Math.round(Math.max(...shares)) : null;
});
</script>

<template>
  <div v-if="percent !== null" class="flex min-w-[140px] items-center gap-2">
    <Progress :model-value="Math.min(100, percent)" :class="cn('flex-1', percent >= 80 && '[&>*]:bg-warning', percent >= 100 && '[&>*]:bg-destructive')" />
    <span class="w-10 text-right text-xs text-muted-foreground tabular-nums">{{ percent }}%</span>
  </div>
  <span v-else class="text-xs text-muted-foreground">No limit</span>
</template>
