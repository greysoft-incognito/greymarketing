<script setup lang="ts">
import type { CampaignStatus } from '@/api';
import type { BadgeVariants } from '@/components/ui/badge';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';

const props = defineProps<{ status: CampaignStatus }>();

const STYLES: Record<CampaignStatus, { label: string; variant: BadgeVariants['variant']; dot?: boolean }> = {
  draft: { label: 'Draft', variant: 'default' },
  scheduled: { label: 'Scheduled', variant: 'brand' },
  preparing: { label: 'Preparing', variant: 'info', dot: true },
  sending: { label: 'Sending', variant: 'info', dot: true },
  completed: { label: 'Sent', variant: 'success' },
  cancelled: { label: 'Cancelled', variant: 'warning' },
  failed: { label: 'Failed', variant: 'destructive' },
};

const style = computed(() => STYLES[props.status]);
</script>

<template>
  <Badge :variant="style.variant" :dot="style.dot ?? false">
    {{ style.label }}
  </Badge>
</template>
