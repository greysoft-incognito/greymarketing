<script setup lang="ts">
import { Label } from '@/components/ui/label';

defineProps<{
  id: string;
  label: string;
  hint?: string;
  error?: string | undefined;
}>();
</script>

<template>
  <div class="grid min-w-0 gap-1.5">
    <div class="flex items-center justify-between gap-2">
      <Label :for="id">{{ label }}</Label>
      <slot name="aside" />
    </div>
    <slot />
    <p v-if="error" class="text-xs text-destructive">
      {{ error }}
    </p>
    <p v-else-if="hint" class="text-xs text-muted-foreground">
      {{ hint }}
    </p>
  </div>
</template>
