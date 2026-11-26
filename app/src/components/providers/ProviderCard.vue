<script setup lang="ts">
import type { Provider, ProviderConnection } from '@/api';
import { useClipboard } from '@vueuse/core';
import { Copy, KeyRound, Loader2, MoreHorizontal, Pencil, Send, Star, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import ChannelLabel from '@/components/app/ChannelLabel.vue';
import ProviderLogo from '@/components/app/ProviderLogo.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { formatRelative } from '@/lib/format';
import { toast } from '@/lib/notify';

const props = defineProps<{
  connection: ProviderConnection;
  provider: Provider | undefined;
  testing: boolean;
  canManage: boolean;
}>();

defineEmits<{
  test: [];
  edit: [];
  sendTest: [];
  makeDefault: [];
  remove: [];
}>();

const { copy } = useClipboard({ legacy: true });

const status = computed(() => ({
  active: { label: 'Connected', variant: 'success' },
  failed: { label: 'Needs attention', variant: 'warning' },
  untested: { label: 'Not tested', variant: 'default' },
} as const)[props.connection.status]);

const channelName = computed(() => (props.connection.channel === 'sms' ? 'SMS' : 'email'));

/**
 * The settings worth showing at a glance, such as the sender.
 */
const details = computed(() => {
  const settings = props.connection.settings;
  const lines: string[] = [];

  if (settings.sender_id) {
    lines.push(`Sender: ${settings.sender_id}`);
  }

  if (settings.from_email) {
    lines.push(`From: ${settings.from_name ? `${settings.from_name} <${settings.from_email}>` : settings.from_email}`);
  }

  return lines;
});

const secret = computed(() => {
  const key = props.provider?.credentials.find(field => field.secret)?.key;

  return key ? props.connection.credentials[key] : null;
});

async function copyUrl(url: string, label: string) {
  await copy(url);
  toast.success(`${label} copied.`);
}
</script>

<template>
  <Card>
    <CardContent class="grid grid-cols-1 gap-3.5 py-5">
      <div class="flex items-start gap-3">
        <ProviderLogo :provider="connection.provider" :name="provider?.name" />
        <div class="grid min-w-0 flex-1 gap-1.5">
          <div class="flex items-center justify-between gap-2">
            <span class="truncate font-semibold">{{ connection.name }}</span>
            <Badge :variant="status.variant" class="shrink-0">
              <span class="size-1.5 rounded-full bg-current" />
              {{ status.label }}
            </Badge>
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <ChannelLabel :channel="connection.channel" />
            <span class="text-xs text-muted-foreground">{{ provider?.name ?? connection.provider }}</span>
            <Badge v-if="connection.is_default" variant="brand">
              Default for {{ channelName }}
            </Badge>
          </div>
        </div>
      </div>

      <div v-if="details.length || secret" class="grid gap-1 text-[13px]">
        <span v-for="line in details" :key="line" class="truncate">{{ line }}</span>
        <span v-if="secret" class="flex items-center gap-1.5 text-xs text-muted-foreground">
          <KeyRound class="size-3.5" />
          <span class="font-mono">{{ secret }}</span>
        </span>
      </div>

      <div class="flex items-center justify-between gap-2">
        <span
          class="line-clamp-2 min-w-0 flex-1 text-xs"
          :class="connection.status === 'failed' ? 'text-warning' : 'text-muted-foreground'"
          :title="connection.last_error ?? undefined"
        >
          <template v-if="connection.status === 'failed'">Last test failed: {{ connection.last_error }}</template>
          <template v-else-if="connection.last_tested_at">Tested {{ formatRelative(connection.last_tested_at) }}</template>
          <template v-else>Not tested yet</template>
        </span>
        <div v-if="canManage" class="flex shrink-0 gap-1">
          <Button variant="secondary" size="sm" :disabled="testing" @click="$emit('test')">
            <Loader2 v-if="testing" class="animate-spin" />
            Test connection
          </Button>
          <DropdownMenu>
            <DropdownMenuTrigger as-child>
              <Button variant="ghost" size="icon" class="size-8" aria-label="More actions">
                <MoreHorizontal />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="w-60">
              <DropdownMenuItem @select="$emit('edit')">
                <Pencil />
                Edit keys and settings
              </DropdownMenuItem>
              <DropdownMenuItem @select="$emit('sendTest')">
                <Send />
                Send a test message
              </DropdownMenuItem>
              <DropdownMenuItem v-if="!connection.is_default" @select="$emit('makeDefault')">
                <Star />
                Make default for {{ channelName }}
              </DropdownMenuItem>
              <template v-if="connection.status_url || connection.inbound_url">
                <DropdownMenuSeparator />
                <DropdownMenuItem v-if="connection.status_url" @select="copyUrl(connection.status_url, 'Delivery report URL')">
                  <Copy />
                  Copy delivery report URL
                </DropdownMenuItem>
                <DropdownMenuItem v-if="connection.inbound_url" @select="copyUrl(connection.inbound_url, 'Inbound SMS URL')">
                  <Copy />
                  Copy inbound SMS URL
                </DropdownMenuItem>
              </template>
              <DropdownMenuSeparator />
              <DropdownMenuItem variant="destructive" @select="$emit('remove')">
                <Trash2 />
                Remove
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </div>
    </CardContent>
  </Card>
</template>
