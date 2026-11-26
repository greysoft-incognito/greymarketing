<script setup lang="ts" generic="T extends string">
import type { Component } from 'vue';
import { cn } from '@/lib/utils';

defineProps<{
  options: { value: T; label: string; icon?: Component }[];
  label: string;
}>();

const model = defineModel<T>({ required: true });
</script>

<template>
  <div role="tablist" :aria-label="label" class="inline-flex flex-wrap gap-0.5 rounded-lg bg-muted p-[3px]">
    <button
      v-for="option in options"
      :key="option.value"
      type="button"
      role="tab"
      :aria-selected="model === option.value"
      :class="cn(
        'inline-flex cursor-pointer items-center gap-1.5 rounded-md px-3 py-1.5 text-[13px] font-medium text-muted-foreground transition-colors [&>svg]:size-3.5',
        model === option.value && 'bg-card text-foreground shadow-card',
      )"
      @click="model = option.value"
    >
      <component :is="option.icon" v-if="option.icon" />
      {{ option.label }}
    </button>
  </div>
</template>
