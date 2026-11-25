<script setup lang="ts">
withDefaults(defineProps<{ size?: number }>(), { size: 28 });
</script>

<template>
  <svg :width="size" :height="size" viewBox="0 0 100 100" class="shrink-0" role="img" aria-label="Greysoft">
    <circle cx="27" cy="27" r="19" fill="var(--brand-grey)" />
    <circle cx="73" cy="27" r="19" fill="var(--brand)" />
    <circle cx="27" cy="73" r="19" fill="var(--brand-grey)" />
    <circle cx="73" cy="73" r="19" fill="var(--brand-grey)" />
  </svg>
</template>
