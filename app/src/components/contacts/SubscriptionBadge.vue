<script setup lang="ts">
import { Badge } from '@/components/ui/badge';

defineProps<{
  reachable: boolean;
  subscribed: boolean;
}>();
</script>

<template>
  <span v-if="!reachable" class="text-muted-foreground">–</span>
  <Badge v-else-if="subscribed" variant="success">
    Subscribed
  </Badge>
  <Badge v-else>
    Unsubscribed
  </Badge>
</template>
