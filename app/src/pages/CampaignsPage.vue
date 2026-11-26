<script setup lang="ts">
import type { Campaign, CampaignStatus } from '@/api';
import { usePagination } from 'alova/client';
import { BarChart3, Ban, MoreHorizontal, Pencil, Plus, Send, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import { campaigns, forget, workspaces } from '@/api';
import CampaignStatusBadge from '@/components/app/CampaignStatusBadge.vue';
import ChannelLabel from '@/components/app/ChannelLabel.vue';
import ConfirmDialog from '@/components/app/ConfirmDialog.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import PaginationFooter from '@/components/app/PaginationFooter.vue';
import RefreshButton from '@/components/app/RefreshButton.vue';
import SegmentedTabs from '@/components/app/SegmentedTabs.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableEmpty, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formatDateTime, formatNumber } from '@/lib/format';
import { notifyError, toast } from '@/lib/notify';
import { useWorkspace } from '@/stores/workspace';

const router = useRouter();
const workspace = useWorkspace();
const id = workspace.current!.id;

const status = ref<CampaignStatus | 'all'>('all');

const statuses = [
  { value: 'all' as const, label: 'All' },
  { value: 'draft' as const, label: 'Drafts' },
  { value: 'scheduled' as const, label: 'Scheduled' },
  { value: 'sending' as const, label: 'Sending' },
  { value: 'completed' as const, label: 'Sent' },
];

const {
  data: rows,
  page,
  pageSize,
  total,
  pageCount,
  loading,
  error,
  refresh: refreshRows,
} = usePagination(
  (pageNumber, perPage) => workspaces.campaigns(id, {
    page: pageNumber,
    limit: perPage,
    ...(status.value === 'all' ? {} : { status: status.value }),
  }),
  {
    watchingStates: [status],
    initialPageSize: 20,
    total: response => response.meta.total,
    data: response => response.data,
  },
);

const editable = (campaign: Campaign) => ['draft', 'scheduled'].includes(campaign.status);
const cancellable = (campaign: Campaign) => ['scheduled', 'preparing', 'sending'].includes(campaign.status);

/**
 * Drafts open in the builder, everything else opens its report.
 */
function open(campaign: Campaign) {
  router.push(campaign.status === 'draft'
    ? { name: 'campaigns.edit', params: { campaign: campaign.id } }
    : { name: 'campaigns.report', params: { campaign: campaign.id } });
}

function when(campaign: Campaign): string {
  if (campaign.started_at) {
    return formatDateTime(campaign.started_at);
  }

  return campaign.scheduled_at ? formatDateTime(campaign.scheduled_at) : 'Not scheduled';
}

const empty = computed(() => !loading.value && !error.value && !rows.value.length);

/**
 * Fetch every page again, skipping anything cached.
 */
async function refresh() {
  await forget('campaigns.list');
  await refreshRows();
}

const confirm = ref<{ title: string; description: string; action: string; run: () => Promise<unknown> } | null>(null);
const confirmOpen = ref(false);

function ask(options: NonNullable<typeof confirm.value>) {
  confirm.value = options;
  confirmOpen.value = true;
}

function cancel(campaign: Campaign) {
  ask({
    title: `Cancel ${campaign.name}?`,
    description: 'Messages not sent yet are skipped. Messages already sent are not affected.',
    action: 'Cancel campaign',
    run: async () => {
      try {
        await campaigns.cancel(id, campaign.id);
        await refresh();
        toast.success(`${campaign.name} cancelled.`);
      } catch (e) {
        notifyError(e);
      }
    },
  });
}

function remove(campaign: Campaign) {
  ask({
    title: `Delete ${campaign.name}?`,
    description: 'Its report and delivery records are deleted too.',
    action: 'Delete campaign',
    run: async () => {
      try {
        await campaigns.remove(id, campaign.id);
        await refresh();
        toast.success(`${campaign.name} deleted.`);
      } catch (e) {
        notifyError(e);
      }
    },
  });
}
</script>

<template>
  <PageHeader title="Campaigns" description="Send an SMS or email to your lists now or at a set time.">
    <template #actions>
      <RefreshButton :refresh="refresh" />
      <Button as-child>
        <RouterLink :to="{ name: 'campaigns.new' }">
          <Plus />
          New campaign
        </RouterLink>
      </Button>
    </template>
  </PageHeader>

  <SegmentedTabs v-model="status" :options="statuses" label="Filter by status" class="justify-self-start" />

  <Alert v-if="error" variant="destructive" class="flex items-center justify-between gap-3">
    <AlertDescription>We couldn't load your campaigns. {{ error.message }}</AlertDescription>
    <Button variant="secondary" size="sm" class="shrink-0" @click="refresh">
      Try again
    </Button>
  </Alert>

  <Card class="p-2">
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead>Campaign</TableHead>
          <TableHead class="hidden md:table-cell">
            Channel
          </TableHead>
          <TableHead>Status</TableHead>
          <TableHead class="hidden text-right md:table-cell">
            Recipients
          </TableHead>
          <TableHead class="hidden md:table-cell">
            Sent or scheduled
          </TableHead>
          <TableHead class="w-12" />
        </TableRow>
      </TableHeader>
      <TableBody>
        <template v-if="rows.length">
          <TableRow v-for="campaign in rows" :key="campaign.id" class="cursor-pointer" @click="open(campaign)">
            <TableCell>
              <RouterLink
                :to="campaign.status === 'draft' ? { name: 'campaigns.edit', params: { campaign: campaign.id } } : { name: 'campaigns.report', params: { campaign: campaign.id } }"
                class="font-medium"
                @click.stop
              >
                {{ campaign.name }}
              </RouterLink>
              <div class="max-w-[200px] truncate sm:max-w-[280px] text-xs text-muted-foreground">
                <span class="md:hidden">{{ campaign.channel === 'sms' ? 'SMS' : 'Email' }} · {{ when(campaign) }} · </span>{{ campaign.lists?.map(list => list.name).join(', ') || 'No lists yet' }}
              </div>
            </TableCell>
            <TableCell class="hidden md:table-cell">
              <ChannelLabel :channel="campaign.channel" />
            </TableCell>
            <TableCell><CampaignStatusBadge :status="campaign.status" /></TableCell>
            <TableCell class="hidden text-right tabular-nums md:table-cell">
              {{ campaign.recipients_count ? formatNumber(campaign.recipients_count) : '–' }}
            </TableCell>
            <TableCell class="hidden text-[13px] whitespace-nowrap text-muted-foreground md:table-cell">
              {{ when(campaign) }}
            </TableCell>
            <TableCell class="text-right" @click.stop>
              <DropdownMenu>
                <DropdownMenuTrigger as-child>
                  <Button variant="ghost" size="icon" class="size-8" :aria-label="`More for ${campaign.name}`">
                    <MoreHorizontal />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                  <DropdownMenuItem v-if="editable(campaign)" as-child>
                    <RouterLink :to="{ name: 'campaigns.edit', params: { campaign: campaign.id } }">
                      <Pencil />
                      Edit
                    </RouterLink>
                  </DropdownMenuItem>
                  <DropdownMenuItem v-if="campaign.status !== 'draft'" as-child>
                    <RouterLink :to="{ name: 'campaigns.report', params: { campaign: campaign.id } }">
                      <BarChart3 />
                      View report
                    </RouterLink>
                  </DropdownMenuItem>
                  <DropdownMenuItem v-if="cancellable(campaign)" @select="cancel(campaign)">
                    <Ban />
                    Cancel
                  </DropdownMenuItem>
                  <template v-if="!['preparing', 'sending'].includes(campaign.status)">
                    <DropdownMenuSeparator />
                    <DropdownMenuItem variant="destructive" @select="remove(campaign)">
                      <Trash2 />
                      Delete
                    </DropdownMenuItem>
                  </template>
                </DropdownMenuContent>
              </DropdownMenu>
            </TableCell>
          </TableRow>
        </template>
        <template v-else-if="loading">
          <TableRow v-for="n in 4" :key="n">
            <TableCell :colspan="6">
              <Skeleton class="h-9" />
            </TableCell>
          </TableRow>
        </template>
        <TableEmpty v-if="empty" :colspan="6">
          <div class="grid place-items-center gap-3 py-6">
            <span class="grid size-11 place-items-center rounded-full bg-brand-soft text-brand"><Send class="size-5" /></span>
            <span>{{ status === 'all' ? 'No campaigns yet.' : 'No campaigns here.' }}</span>
            <Button v-if="status === 'all'" as-child size="sm">
              <RouterLink :to="{ name: 'campaigns.new' }">
                <Plus />
                New campaign
              </RouterLink>
            </Button>
          </div>
        </TableEmpty>
      </TableBody>
    </Table>
    <PaginationFooter v-model="page" :page-size="pageSize" :total="total" :page-count="pageCount" noun="campaigns" />
  </Card>

  <ConfirmDialog
    v-if="confirm"
    v-model:open="confirmOpen"
    :title="confirm.title"
    :description="confirm.description"
    :action="confirm.action"
    :run="confirm.run"
  />
</template>
