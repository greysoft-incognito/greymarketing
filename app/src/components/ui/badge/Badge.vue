<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import type { BadgeVariants } from '.';
import { cn } from '@/lib/utils';
import { badgeVariants } from '.';

const props = defineProps<{
  variant?: BadgeVariants['variant'];
  dot?: boolean;
  class?: HTMLAttributes['class'];
}>();
</script>

<template>
  <span data-slot="badge" :class="cn(badgeVariants({ variant }), props.class)">
    <span v-if="dot" class="size-1.5 rounded-full bg-current" />
    <slot />
  </span>
</template>
