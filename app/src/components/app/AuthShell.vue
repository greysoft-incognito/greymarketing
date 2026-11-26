<script setup lang="ts">
import { useBranding } from '@/composables/useBranding';
import BrandMark from './BrandMark.vue';

const { image } = useBranding();
</script>

<template>
  <div class="grid min-h-screen md:grid-cols-2">
    <div class="relative hidden overflow-hidden bg-ink text-ink-foreground md:block">
      <img :src="image" alt="" class="absolute inset-0 size-full object-cover">
      <div class="absolute inset-0 bg-gradient-to-r from-ink/85 via-ink/55 to-ink/10" />
      <div class="relative flex h-full flex-col justify-between gap-8 p-10">
        <div class="flex items-center gap-2">
          <BrandMark :size="32" />
          <b class="text-base">GreyMarketing</b>
        </div>
        <slot name="aside" />
        <div class="text-xs opacity-60">
          © {{ new Date().getFullYear() }} Greysoft
        </div>
      </div>
    </div>
    <div class="grid place-items-center px-4 py-10">
      <div class="grid w-full max-w-[360px] gap-4">
        <div class="flex items-center justify-center gap-2 md:hidden">
          <BrandMark :size="28" />
          <b class="text-base">GreyMarketing</b>
        </div>
        <slot />
      </div>
    </div>
  </div>
</template>
