<script setup lang="ts">
import { computed } from 'vue';
import { formatDay, formatNumber } from '@/lib/format';

const props = defineProps<{
  days: Array<{ date: string; email: number; sms: number }>;
}>();

const W = 720;
const H = 220;
const LEFT = 44;
const RIGHT = 8;
const TOP = 10;
const BOTTOM = 26;

/**
 * A round axis maximum just above the busiest day.
 */
const max = computed(() => {
  const peak = Math.max(0, ...props.days.map(day => day.email + day.sms));

  if (peak <= 4) {
    return 4;
  }

  const step = 10 ** Math.floor(Math.log10(peak));

  return Math.ceil(peak / step) * step;
});

const ticks = computed(() => [0, 0.25, 0.5, 0.75, 1].map(ratio => Math.round(max.value * ratio)));

const band = computed(() => (W - LEFT - RIGHT) / Math.max(1, props.days.length));

function y(value: number) {
  return TOP + (H - TOP - BOTTOM) * (1 - value / max.value);
}

function height(value: number) {
  return (H - TOP - BOTTOM) * value / max.value;
}

function tickLabel(value: number) {
  return value >= 1000 ? `${value / 1000}k` : String(value);
}

const bars = computed(() => props.days.map((day, index) => {
  const x = LEFT + index * band.value + band.value * 0.18;

  return {
    ...day,
    x,
    width: band.value * 0.64,
    label: new Date(day.date).getDate(),
    title: formatDay(day.date),
    isLast: index === props.days.length - 1,
  };
}));
</script>

<template>
  <svg :viewBox="`0 0 ${W} ${H}`" class="block h-auto w-full" role="img" :aria-label="`Messages sent per day over the last ${days.length} days`">
    <g v-for="tick in ticks" :key="tick">
      <line :x1="LEFT" :x2="W - RIGHT" :y1="y(tick)" :y2="y(tick)" stroke="var(--border)" />
      <text :x="LEFT - 8" :y="y(tick) + 4" text-anchor="end" class="fill-muted-foreground text-[11px]">{{ tickLabel(tick) }}</text>
    </g>
    <g v-for="bar in bars" :key="bar.date">
      <rect v-if="bar.sms" :x="bar.x" :y="y(bar.sms)" :width="bar.width" :height="height(bar.sms)" rx="2" fill="var(--brand-grey)">
        <title>{{ bar.title }} · SMS {{ formatNumber(bar.sms) }}</title>
      </rect>
      <rect v-if="bar.email" :x="bar.x" :y="y(bar.sms + bar.email)" :width="bar.width" :height="Math.max(0, height(bar.email) - (bar.sms ? 1 : 0))" rx="2" fill="var(--brand)">
        <title>{{ bar.title }} · Email {{ formatNumber(bar.email) }}</title>
      </rect>
      <text
        :x="bar.x + bar.width / 2"
        :y="H - 8"
        text-anchor="middle"
        class="text-[11px]"
        :class="bar.isLast ? 'fill-foreground font-semibold' : 'fill-muted-foreground'"
      >{{ bar.label }}</text>
    </g>
  </svg>
</template>
