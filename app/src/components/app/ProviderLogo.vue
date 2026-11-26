<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
  provider: string;
  name?: string;
  class?: string;
}>();

const tones: Record<string, string> = {
  africastalking: 'bg-[#f39c12]/15 text-[#c27500]',
  sendpulse: 'bg-[#1a73e8]/12 text-[#1a5fc8]',
  termii: 'bg-[#0bab64]/12 text-[#0b8a52]',
  twilio: 'bg-[#f22f46]/12 text-[#d1253a]',
  mailgun: 'bg-[#c02126]/12 text-[#b01e23]',
  smtp: 'bg-muted text-foreground',
};

const initials = computed(() => {
  const words = (props.name ?? props.provider).replace(/[^a-z\s]/gi, '').split(/\s+/).filter(Boolean);

  return words.length > 1
    ? words.slice(0, 2).map(word => word[0]!.toUpperCase()).join('')
    : (words[0] ?? '?').slice(0, 2).toUpperCase();
});
</script>

<template>
  <span
    :class="cn('grid size-10 shrink-0 place-items-center rounded-lg text-sm font-semibold', tones[provider] ?? 'bg-brand-soft text-brand', props.class)"
    aria-hidden="true"
  >
    {{ initials }}
  </span>
</template>
