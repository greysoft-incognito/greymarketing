<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { useVModel } from '@vueuse/core';
import { cn } from '@/lib/utils';

const props = defineProps<{
  defaultValue?: string | number;
  modelValue?: string | number;
  class?: HTMLAttributes['class'];
}>();

const emits = defineEmits<{
  (e: 'update:modelValue', payload: string | number): void;
}>();

const modelValue = useVModel(props, 'modelValue', emits, {
  passive: true,
  defaultValue: props.defaultValue,
});
</script>

<template>
  <input
    v-model="modelValue"
    data-slot="input"
    :class="cn(
      'h-9 w-full min-w-0 rounded-md bg-input px-3 text-sm text-foreground outline-none transition-[box-shadow] placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground disabled:cursor-not-allowed disabled:opacity-50 file:mr-3 file:border-0 file:bg-transparent file:text-sm file:font-medium',
      'focus-visible:ring-2 focus-visible:ring-ring',
      'aria-invalid:ring-2 aria-invalid:ring-destructive/60',
      props.class,
    )"
  >
</template>
