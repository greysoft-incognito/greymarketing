<script setup lang="ts">
import { Check } from 'lucide-vue-next';
import { cn } from '@/lib/utils';

defineProps<{
  steps: string[];
  current: number;
}>();
</script>

<template>
  <ol class="flex flex-wrap items-center gap-x-6 gap-y-2">
    <li
      v-for="(label, index) in steps"
      :key="label"
      class="flex items-center gap-2 text-sm"
      :aria-current="index + 1 === current ? 'step' : undefined"
    >
      <span
        :class="cn(
          'grid size-6 place-items-center rounded-full text-xs font-semibold',
          index + 1 < current && 'bg-brand text-white',
          index + 1 === current && 'bg-brand-soft text-brand',
          index + 1 > current && 'bg-muted text-muted-foreground',
        )"
      >
        <Check v-if="index + 1 < current" class="size-3.5" />
        <template v-else>{{ index + 1 }}</template>
      </span>
      <span :class="index + 1 === current ? 'font-medium' : 'text-muted-foreground'">{{ label }}</span>
    </li>
  </ol>
</template>
