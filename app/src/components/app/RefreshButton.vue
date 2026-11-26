<script setup lang="ts">
import { RefreshCw } from 'lucide-vue-next';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

const props = defineProps<{
  refresh: () => Promise<unknown>;
}>();

const busy = ref(false);

async function run() {
  busy.value = true;

  try {
    await props.refresh();
  } finally {
    busy.value = false;
  }
}
</script>

<template>
  <Button variant="secondary" size="icon" :disabled="busy" title="Refresh" aria-label="Refresh" @click="run">
    <RefreshCw :class="cn(busy && 'animate-spin')" />
  </Button>
</template>
