<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { ChevronDown } from 'lucide-vue-next';
import { cn } from '@/lib/utils';

defineOptions({ inheritAttrs: false });

const props = defineProps<{ class?: HTMLAttributes['class'] }>();
const model = defineModel<string | null>();
</script>

<template>
  <div :class="cn('relative min-w-0', props.class)">
    <select
      v-model="model"
      data-slot="native-select"
      v-bind="$attrs"
      class="h-9 w-full min-w-0 cursor-pointer appearance-none rounded-md bg-input pr-9 pl-3 text-sm text-foreground outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
    >
      <slot />
    </select>
    <ChevronDown class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-muted-foreground" />
  </div>
</template>
