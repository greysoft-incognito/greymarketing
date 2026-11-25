<script setup lang="ts">
defineProps<{
  title: string;
  description?: string;
}>();
</script>

<template>
  <div class="flex flex-wrap items-end gap-4">
    <div class="grid min-w-[220px] flex-1 gap-1">
      <h1 class="text-2xl font-semibold">
        {{ title }}
      </h1>
      <p v-if="description" class="text-muted-foreground">
        {{ description }}
      </p>
    </div>
    <div v-if="$slots.actions" class="flex flex-wrap gap-2">
      <slot name="actions" />
    </div>
  </div>
</template>
