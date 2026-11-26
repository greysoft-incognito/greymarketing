<script setup lang="ts">
import type { Component } from 'vue';
import { ArrowLeft, BarChart3, Layers, LayoutDashboard, Settings2, Users } from 'lucide-vue-next';
import { useRoute } from 'vue-router';
import BrandMark from '@/components/app/BrandMark.vue';
import { Button } from '@/components/ui/button';
import { useSession } from '@/stores/session';

interface NavItem {
  to: string;
  label: string;
  icon: Component;
}

const emit = defineEmits<{ navigate: [] }>();

const route = useRoute();
const session = useSession();

const nav: NavItem[] = [
  { to: 'admin', label: 'Overview', icon: LayoutDashboard },
  { to: 'admin.customers', label: 'Customers', icon: Users },
  { to: 'admin.plans', label: 'Plans', icon: Layers },
  { to: 'admin.usage', label: 'Usage', icon: BarChart3 },
  { to: 'admin.settings', label: 'System settings', icon: Settings2 },
];
</script>

<template>
  <div class="flex h-full flex-col gap-1 bg-sidebar p-3 text-sidebar-foreground">
    <div class="mb-2.5 flex items-center gap-2.5 rounded-lg bg-muted p-2">
      <BrandMark />
      <span class="min-w-0 flex-1">
        <span class="block truncate text-[13px] font-semibold">GreyMarketing</span>
        <span class="block truncate text-xs text-muted-foreground">Admin console</span>
      </span>
    </div>

    <nav aria-label="Admin" class="grid gap-0.5">
      <RouterLink
        v-for="item in nav"
        :key="item.to"
        :to="{ name: item.to }"
        class="flex items-center gap-2.5 rounded-md px-2 py-[7px] font-medium no-underline transition-colors"
        :class="route.name === item.to ? 'bg-brand-soft text-brand' : 'text-muted-foreground hover:bg-background hover:text-foreground'"
        @click="emit('navigate')"
      >
        <component :is="item.icon" class="size-4" />
        {{ item.label }}
      </RouterLink>
    </nav>

    <div class="mt-auto grid gap-2 rounded-lg bg-background p-3">
      <div class="text-xs text-muted-foreground">
        Signed in as staff
      </div>
      <div class="truncate text-[13px] font-medium">
        {{ session.name }}
      </div>
      <Button variant="secondary" size="sm" as-child>
        <RouterLink :to="{ name: 'dashboard' }" @click="emit('navigate')">
          <ArrowLeft />
          Back to app
        </RouterLink>
      </Button>
    </div>
  </div>
</template>
