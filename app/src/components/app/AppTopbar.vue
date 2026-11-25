<script setup lang="ts">
import { LogOut, Menu, Moon, Settings2, Sun } from 'lucide-vue-next';
import { useRouter } from 'vue-router';
import { Avatar } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { useTheme } from '@/composables/useTheme';
import { useSession } from '@/stores/session';
import { useWorkspace } from '@/stores/workspace';

defineProps<{ title: string }>();

const emit = defineEmits<{ menu: [] }>();

const router = useRouter();
const session = useSession();
const workspace = useWorkspace();
const theme = useTheme();

async function signOut() {
  await session.logout();
  await router.push({ name: 'login' });
}
</script>

<template>
  <header class="sticky top-0 z-20 flex h-14 items-center gap-3 bg-background px-4 md:px-8">
    <Button variant="ghost" size="icon" class="lg:hidden" aria-label="Open menu" @click="emit('menu')">
      <Menu />
    </Button>
    <div class="flex min-w-0 items-center gap-1.5 overflow-hidden text-[13px] whitespace-nowrap text-muted-foreground">
      <span class="truncate">{{ workspace.current?.name }}</span>
      <span aria-hidden="true">/</span>
      <b class="font-medium text-foreground">{{ title }}</b>
    </div>
    <span class="flex-1" />
    <Button variant="ghost" size="icon" :aria-label="theme.isDark.value ? 'Use light theme' : 'Use dark theme'" @click="theme.toggle()">
      <Sun v-if="theme.isDark.value" />
      <Moon v-else />
    </Button>
    <DropdownMenu>
      <DropdownMenuTrigger as-child>
        <button type="button" class="rounded-full outline-none focus-visible:ring-2 focus-visible:ring-ring" aria-label="Account menu">
          <Avatar :name="session.name" />
        </button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" class="w-56">
        <DropdownMenuLabel class="grid gap-0.5">
          <span class="text-sm font-medium text-foreground">{{ session.name }}</span>
          <span class="truncate font-normal">{{ session.user?.email }}</span>
        </DropdownMenuLabel>
        <DropdownMenuSeparator />
        <DropdownMenuItem @select="router.push({ name: 'settings' })">
          <Settings2 />
          Workspace settings
        </DropdownMenuItem>
        <DropdownMenuItem @select="signOut">
          <LogOut />
          Sign out
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  </header>
</template>
