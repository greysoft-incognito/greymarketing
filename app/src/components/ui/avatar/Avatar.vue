<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { AvatarFallback, AvatarImage, AvatarRoot } from 'reka-ui';
import { computed } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
  name?: string | null;
  src?: string | null;
  class?: HTMLAttributes['class'];
}>();

const initials = computed(() => (props.name ?? '?')
  .split(/\s+/)
  .filter(Boolean)
  .slice(0, 2)
  .map(part => part[0]?.toUpperCase())
  .join(''));
</script>

<template>
  <AvatarRoot
    data-slot="avatar"
    :class="cn('relative flex size-8 shrink-0 overflow-hidden rounded-full', props.class)"
  >
    <AvatarImage v-if="src" :src="src" class="aspect-square size-full" />
    <AvatarFallback class="flex size-full items-center justify-center rounded-full bg-brand-soft text-xs font-semibold text-brand">
      {{ initials }}
    </AvatarFallback>
  </AvatarRoot>
</template>
