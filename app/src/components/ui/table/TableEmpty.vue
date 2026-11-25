<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = withDefaults(defineProps<{
  colspan?: number;
  class?: HTMLAttributes['class'];
}>(), {
  colspan: 1,
});
</script>

<template>
  <tr>
    <td :colspan="colspan" :class="cn('p-8 text-center text-sm text-muted-foreground', props.class)">
      <slot />
    </td>
  </tr>
</template>
