<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{ class?: HTMLAttributes['class'] }>();
const model = defineModel<string>();
</script>

<template>
  <textarea
    v-model="model"
    data-slot="textarea"
    :class="cn(
      'min-h-20 w-full min-w-0 rounded-md bg-input px-3 py-2 text-sm text-foreground outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50',
      props.class,
    )"
  />
</template>
