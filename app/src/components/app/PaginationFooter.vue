<script setup lang="ts">
import type { Paginated } from '@/api';
import { Button } from '@/components/ui/button';
import { formatNumber } from '@/lib/format';

defineProps<{
  meta: Paginated<unknown>['meta'] | undefined;
  noun?: string;
}>();

const page = defineModel<number>({ required: true });
</script>

<template>
  <div v-if="meta && meta.total" class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
    <span class="text-xs text-muted-foreground tabular-nums">
      Showing {{ formatNumber(meta.from ?? 0) }}–{{ formatNumber(meta.to ?? 0) }} of {{ formatNumber(meta.total) }} {{ noun }}
    </span>
    <div class="flex gap-2">
      <Button variant="secondary" size="sm" :disabled="meta.current_page <= 1" @click="page = meta.current_page - 1">
        Previous
      </Button>
      <Button variant="secondary" size="sm" :disabled="meta.current_page >= meta.last_page" @click="page = meta.current_page + 1">
        Next
      </Button>
    </div>
  </div>
</template>
