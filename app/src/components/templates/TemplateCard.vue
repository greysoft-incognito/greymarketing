<script setup lang="ts">
import type { Template } from '@/api';
import { MoreHorizontal, Pencil, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import ChannelLabel from '@/components/app/ChannelLabel.vue';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { formatRelative } from '@/lib/format';

const props = defineProps<{
  template: Template;
}>();

defineEmits<{ remove: [] }>();

const summary = computed(() => {
  if (props.template.channel === 'sms') {
    const parts = props.template.sms?.segments ?? 1;

    return `${parts} segment${parts === 1 ? '' : 's'}`;
  }

  return props.template.html ? 'HTML' : 'Plain text';
});
</script>

<template>
  <article class="group relative grid overflow-hidden rounded-xl bg-card shadow-card transition-shadow hover:shadow-md">
    <div
      class="grid h-[120px] overflow-hidden bg-muted p-3.5"
      :class="template.channel === 'sms' ? 'content-end' : 'content-start'"
      aria-hidden="true"
    >
      <div
        v-if="template.channel === 'sms'"
        class="max-w-[85%] justify-self-start rounded-2xl rounded-bl-sm bg-card px-3 py-2 text-xs"
      >
        <p class="line-clamp-3 break-words whitespace-pre-wrap">
          {{ template.body }}
        </p>
      </div>
      <div v-else class="grid gap-1.5 rounded-md bg-card p-2.5">
        <span class="truncate text-xs font-medium">{{ template.subject }}</span>
        <span class="h-1.5 w-[90%] rounded-sm bg-muted" />
        <span class="h-1.5 w-3/4 rounded-sm bg-muted" />
        <span class="h-1.5 w-1/2 rounded-sm bg-muted" />
      </div>
    </div>
    <div class="grid gap-1.5 p-4">
      <div class="flex items-center justify-between gap-2">
        <RouterLink
          :to="{ name: 'templates.edit', params: { template: template.id } }"
          class="truncate font-semibold after:absolute after:inset-0 focus-visible:outline-none"
        >
          {{ template.name }}
        </RouterLink>
        <ChannelLabel :channel="template.channel" class="shrink-0" />
      </div>
      <div class="flex items-center justify-between gap-2">
        <span class="text-xs text-muted-foreground">{{ summary }} · Edited {{ formatRelative(template.updated_at) }}</span>
        <DropdownMenu>
          <DropdownMenuTrigger as-child>
            <Button variant="ghost" size="icon" class="relative z-10 -my-1.5 size-8" :aria-label="`More for ${template.name}`">
              <MoreHorizontal />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            <DropdownMenuItem as-child>
              <RouterLink :to="{ name: 'templates.edit', params: { template: template.id } }">
                <Pencil />
                Edit
              </RouterLink>
            </DropdownMenuItem>
            <DropdownMenuItem variant="destructive" @select="$emit('remove')">
              <Trash2 />
              Delete
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </div>
    </div>
  </article>
</template>
