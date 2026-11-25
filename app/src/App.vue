<script setup lang="ts">
import { useTitle } from '@vueuse/core';
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { Toaster } from '@/components/ui/sonner';

const route = useRoute();

useTitle(computed(() => route.meta.title ? `${route.meta.title} · GreyMarketing` : 'GreyMarketing'));
</script>

<template>
  <RouterView />
  <Toaster position="bottom-right" />
</template>
