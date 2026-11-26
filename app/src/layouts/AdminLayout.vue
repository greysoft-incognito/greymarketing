<script setup lang="ts">
import { computed, ref } from 'vue';
import { useRoute } from 'vue-router';
import AdminSidebar from '@/components/admin/AdminSidebar.vue';
import AppTopbar from '@/components/app/AppTopbar.vue';
import { Sheet, SheetContent, SheetDescription, SheetTitle } from '@/components/ui/sheet';

const route = useRoute();
const menuOpen = ref(false);

const title = computed(() => route.meta.title ?? '');
</script>

<template>
  <div class="grid min-h-screen lg:grid-cols-[248px_minmax(0,1fr)]">
    <div class="hidden bg-sidebar lg:block">
      <aside class="sticky top-0 h-screen overflow-auto">
        <AdminSidebar />
      </aside>
    </div>

    <Sheet v-model:open="menuOpen">
      <SheetContent side="left">
        <SheetTitle class="sr-only">
          Menu
        </SheetTitle>
        <SheetDescription class="sr-only">
          Admin console navigation
        </SheetDescription>
        <AdminSidebar @navigate="menuOpen = false" />
      </SheetContent>
    </Sheet>

    <div class="flex min-w-0 flex-col">
      <AppTopbar context="Admin" :title="title" @menu="menuOpen = true" />
      <main class="grid w-full max-w-[1200px] content-start gap-5 px-4 pt-6 pb-12 md:px-8">
        <RouterView />
      </main>
    </div>
  </div>
</template>
