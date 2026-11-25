<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
  class?: HTMLAttributes['class'];
}>();
</script>

<template>
  <th
    data-slot="table-head"
    :class="cn('h-10 border-b border-border px-3 text-left align-middle text-[13px] font-medium whitespace-nowrap text-muted-foreground', props.class)"
  >
    <slot />
  </th>
</template>
