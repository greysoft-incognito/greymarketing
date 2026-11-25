<script setup lang="ts">
import BrandMark from '@/components/app/BrandMark.vue';
import { Button } from '@/components/ui/button';
</script>

<template>
  <div class="grid min-h-screen place-items-center px-4">
    <div class="grid justify-items-center gap-3 text-center">
      <BrandMark :size="40" />
      <h1 class="text-2xl font-semibold">
        Page not found
      </h1>
      <p class="text-muted-foreground">
        The link may be old, or the page has moved.
      </p>
      <Button as-child>
        <RouterLink to="/">
          Go to the dashboard
        </RouterLink>
      </Button>
    </div>
  </div>
</template>
