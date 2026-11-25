<script setup lang="ts">
import type { Channel } from '@/api';
import { Mail, MessageSquare } from 'lucide-vue-next';

defineProps<{ channel: Channel }>();
</script>

<template>
  <span
    class="inline-flex items-center gap-1.5 text-[13px] font-medium"
    :class="channel === 'email' ? 'text-brand' : 'text-brand-grey'"
  >
    <Mail v-if="channel === 'email'" class="size-3.5" />
    <MessageSquare v-else class="size-3.5" />
    {{ channel === 'email' ? 'Email' : 'SMS' }}
  </span>
</template>
