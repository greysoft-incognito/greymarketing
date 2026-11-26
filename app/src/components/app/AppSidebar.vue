<script setup lang="ts">
import type { Component } from 'vue';
import { BarChart3, Check, ChevronsUpDown, CreditCard, FileText, LayoutDashboard, Plug, Plus, Send, Settings2, ShieldCheck, Users } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useRoute } from 'vue-router';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Progress } from '@/components/ui/progress';
import NewWorkspaceDialog from '@/components/workspace/NewWorkspaceDialog.vue';
import { formatNumber } from '@/lib/format';
import { useSession } from '@/stores/session';
import { useWorkspace } from '@/stores/workspace';

interface NavItem {
  to: string;
  label: string;
  icon: Component;
}

const emit = defineEmits<{ navigate: [] }>();

const route = useRoute();
const session = useSession();
const workspace = useWorkspace();
const creating = ref(false);

const roles: Record<string, string> = { owner: 'Owner', admin: 'Admin', member: 'Member' };

const workspaceNav: NavItem[] = [
  { to: 'dashboard', label: 'Dashboard', icon: LayoutDashboard },
  { to: 'campaigns', label: 'Campaigns', icon: Send },
  { to: 'contacts', label: 'Contacts', icon: Users },
  { to: 'templates', label: 'Templates', icon: FileText },
  { to: 'reports', label: 'Reports', icon: BarChart3 },
  { to: 'providers', label: 'Providers', icon: Plug },
];

const accountNav: NavItem[] = [
  { to: 'billing', label: 'Billing', icon: CreditCard },
  { to: 'settings', label: 'Workspace settings', icon: Settings2 },
];

const initials = computed(() => (workspace.current?.name ?? '')
  .split(/\s+/)
  .filter(word => word && !/^(workspace|'s)$/i.test(word))
  .slice(0, 2)
  .map(word => word[0]?.toUpperCase())
  .join(''));

/**
 * Emails and SMS sent this month against the plan's combined allowance.
 */
const messages = computed(() => {
  const usage = workspace.billing?.usage;

  if (!usage) {
    return null;
  }

  const used = usage.emails.used + usage.sms.used;
  const limit = usage.emails.limit === null || usage.sms.limit === null ? null : usage.emails.limit + usage.sms.limit;

  return { used, limit, percent: limit ? Math.min(100, Math.round(used / limit * 100)) : 0 };
});

const resetsOn = computed(() => {
  const end = workspace.billing?.period.ends_at;

  return end ? new Intl.DateTimeFormat('en-NG', { day: 'numeric', month: 'short' }).format(new Date(new Date(end).getTime() + 1000)) : '';
});

function isActive(name: string) {
  return route.name === name || String(route.name ?? '').startsWith(`${name}.`);
}
</script>

<template>
  <div class="flex h-full flex-col gap-1 bg-sidebar p-3 text-sidebar-foreground">
    <DropdownMenu>
      <DropdownMenuTrigger as-child>
        <button type="button" class="mb-2.5 flex w-full items-center gap-2.5 rounded-lg bg-muted p-2 text-left outline-none focus-visible:ring-2 focus-visible:ring-ring">
          <span class="grid size-7 shrink-0 place-items-center rounded-[7px] bg-brand text-[13px] font-bold text-white">{{ initials || 'GM' }}</span>
          <span class="min-w-0 flex-1">
            <span class="block truncate text-[13px] font-semibold">{{ workspace.current?.name }}</span>
            <span class="block truncate text-xs text-muted-foreground">{{ workspace.billing?.plan?.name ?? 'No plan' }} plan</span>
          </span>
          <ChevronsUpDown class="size-4 text-muted-foreground" />
        </button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="start" class="w-(--reka-dropdown-menu-trigger-width)">
        <DropdownMenuLabel>Workspaces</DropdownMenuLabel>
        <DropdownMenuItem v-for="item in workspace.list" :key="item.id" @select="workspace.select(item.id)">
          <span class="grid min-w-0 flex-1">
            <span class="truncate">{{ item.name }}</span>
            <span class="truncate text-xs text-muted-foreground">
              {{ [roles[item.role ?? ''], item.plan ? `${item.plan.name} plan` : null].filter(Boolean).join(' · ') }}
            </span>
          </span>
          <Check v-if="item.id === workspace.current?.id" class="text-brand" />
        </DropdownMenuItem>
        <DropdownMenuSeparator />
        <DropdownMenuItem @select="creating = true">
          <Plus />
          New workspace
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>

    <NewWorkspaceDialog v-model:open="creating" />

    <nav aria-label="Workspace" class="grid gap-0.5">
      <RouterLink
        v-for="item in workspaceNav"
        :key="item.to"
        :to="{ name: item.to }"
        class="flex items-center gap-2.5 rounded-md px-2 py-[7px] font-medium no-underline transition-colors"
        :class="isActive(item.to) ? 'bg-brand-soft text-brand' : 'text-muted-foreground hover:bg-background hover:text-foreground'"
        @click="emit('navigate')"
      >
        <component :is="item.icon" class="size-4" />
        {{ item.label }}
      </RouterLink>
    </nav>

    <div class="px-2 pt-3 pb-1 text-[11px] font-medium tracking-wider text-muted-foreground uppercase">
      Account
    </div>
    <nav aria-label="Account" class="grid gap-0.5">
      <RouterLink
        v-for="item in accountNav"
        :key="item.to"
        :to="{ name: item.to }"
        class="flex items-center gap-2.5 rounded-md px-2 py-[7px] font-medium no-underline transition-colors"
        :class="isActive(item.to) ? 'bg-brand-soft text-brand' : 'text-muted-foreground hover:bg-background hover:text-foreground'"
        @click="emit('navigate')"
      >
        <component :is="item.icon" class="size-4" />
        {{ item.label }}
      </RouterLink>
      <RouterLink
        v-if="session.isStaff"
        :to="{ name: 'admin' }"
        class="flex items-center gap-2.5 rounded-md px-2 py-[7px] font-medium text-muted-foreground no-underline transition-colors hover:bg-background hover:text-foreground"
        @click="emit('navigate')"
      >
        <ShieldCheck class="size-4" />
        Admin console
      </RouterLink>
    </nav>

    <div v-if="messages" class="mt-auto grid gap-2 rounded-lg bg-background p-3">
      <div class="flex items-center justify-between text-xs">
        <span class="font-medium">Messages this month</span>
        <span v-if="messages.limit" class="text-muted-foreground tabular-nums">{{ messages.percent }}%</span>
      </div>
      <Progress v-if="messages.limit" :model-value="messages.percent" />
      <div class="text-xs text-muted-foreground tabular-nums">
        <template v-if="messages.limit">
          {{ formatNumber(messages.used) }} of {{ formatNumber(messages.limit) }} · resets {{ resetsOn }}
        </template>
        <template v-else>
          {{ formatNumber(messages.used) }} sent · no limit
        </template>
      </div>
      <Button variant="secondary" size="sm" as-child>
        <RouterLink :to="{ name: 'billing' }" @click="emit('navigate')">
          Upgrade plan
        </RouterLink>
      </Button>
    </div>
  </div>
</template>
