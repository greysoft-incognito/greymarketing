<script setup lang="ts">
import { computed, ref } from 'vue';
import { useRoute } from 'vue-router';
import AppSidebar from '@/components/app/AppSidebar.vue';
import AppTopbar from '@/components/app/AppTopbar.vue';
import { Sheet, SheetContent, SheetDescription, SheetTitle } from '@/components/ui/sheet';
import { useWorkspace } from '@/stores/workspace';

const route = useRoute();
const workspace = useWorkspace();
const menuOpen = ref(false);

const title = computed(() => route.meta.title ?? '');
</script>

<template>
  <div class="grid min-h-screen lg:grid-cols-[248px_minmax(0,1fr)]">
    <div class="hidden bg-sidebar lg:block">
      <aside class="sticky top-0 h-screen overflow-auto">
        <AppSidebar />
      </aside>
    </div>

    <Sheet v-model:open="menuOpen">
      <SheetContent side="left">
        <SheetTitle class="sr-only">
          Menu
        </SheetTitle>
        <SheetDescription class="sr-only">
          Workspace navigation
        </SheetDescription>
        <AppSidebar @navigate="menuOpen = false" />
      </SheetContent>
    </Sheet>

    <div class="flex min-w-0 flex-col">
      <AppTopbar :title="title" @menu="menuOpen = true" />
      <main class="grid w-full max-w-[1200px] content-start gap-5 px-4 pt-6 pb-12 md:px-8">
        <RouterView v-if="workspace.current" :key="workspace.current.id" />
      </main>
    </div>
  </div>
</template>
