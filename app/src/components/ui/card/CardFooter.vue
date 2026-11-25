<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
  class?: HTMLAttributes['class'];
}>();
</script>

<template>
  <div
    data-slot="card-footer"
    :class="cn('flex flex-wrap items-center justify-end gap-2 border-t border-border px-5 py-3.5', props.class)"
  >
    <slot />
  </div>
</template>
