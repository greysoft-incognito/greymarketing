<script setup lang="ts">
import { Hammer } from 'lucide-vue-next';
import { useRoute } from 'vue-router';
import PageHeader from '@/components/app/PageHeader.vue';
import { Card } from '@/components/ui/card';

const route = useRoute();
</script>

<template>
  <PageHeader :title="String(route.meta.title ?? '')" />
  <Card class="grid place-items-center gap-2 px-6 py-16 text-center">
    <span class="grid size-10 place-items-center rounded-full bg-muted text-muted-foreground"><Hammer class="size-5" /></span>
    <b>This page is being built</b>
    <p class="max-w-[48ch] text-[13px] text-muted-foreground">
      It follows the approved design and arrives in the next part of the app.
    </p>
  </Card>
</template>
