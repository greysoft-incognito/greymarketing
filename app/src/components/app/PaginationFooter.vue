<script setup lang="ts">
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { formatNumber } from '@/lib/format';

const props = defineProps<{
  pageSize: number;
  total: number | undefined;
  pageCount: number | undefined;
  noun?: string;
}>();

const page = defineModel<number>({ required: true });

const range = computed(() => ({
  from: (page.value - 1) * props.pageSize + 1,
  to: Math.min(page.value * props.pageSize, props.total ?? 0),
}));
</script>

<template>
  <div v-if="total" class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
    <span class="text-xs text-muted-foreground tabular-nums">
      Showing {{ formatNumber(range.from) }}–{{ formatNumber(range.to) }} of {{ formatNumber(total) }} {{ noun }}
    </span>
    <div class="flex gap-2">
      <Button variant="secondary" size="sm" :disabled="page <= 1" @click="page--">
        Previous
      </Button>
      <Button variant="secondary" size="sm" :disabled="page >= (pageCount ?? 1)" @click="page++">
        Next
      </Button>
    </div>
  </div>
</template>
