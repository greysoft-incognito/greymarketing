<script setup lang="ts">
import BrandMark from './BrandMark.vue';
</script>

<template>
  <div class="grid min-h-screen md:grid-cols-2">
    <div class="hidden flex-col justify-between gap-8 bg-ink p-10 text-ink-foreground md:flex">
      <div class="flex items-center gap-2">
        <BrandMark :size="32" />
        <b class="text-base">GreyMarketing</b>
      </div>
      <slot name="aside" />
      <div class="text-xs opacity-60">
        © {{ new Date().getFullYear() }} Greysoft
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
