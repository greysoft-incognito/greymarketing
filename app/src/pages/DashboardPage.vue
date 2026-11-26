<script setup lang="ts">
import { useRequest } from 'alova/client';
import { Check, ChevronRight, Plus, Upload } from 'lucide-vue-next';
import { computed } from 'vue';
import { workspaces } from '@/api';
import CampaignStatusBadge from '@/components/app/CampaignStatusBadge.vue';
import ChannelLabel from '@/components/app/ChannelLabel.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import StatCard from '@/components/app/StatCard.vue';
import VolumeChart from '@/components/app/VolumeChart.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableEmpty, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formatDateTime, formatNumber, percentChange } from '@/lib/format';
import { useSession } from '@/stores/session';
import { useWorkspace } from '@/stores/workspace';

const session = useSession();
const workspace = useWorkspace();
const id = workspace.current!.id;

const stats = useRequest(() => workspaces.stats(id));
const campaigns = useRequest(() => workspaces.campaigns(id, { limit: 5 }));
const connections = useRequest(() => workspaces.connections(id));

const greeting = computed(() => {
  const hour = new Date().getHours();
  const part = hour < 12 ? 'morning' : hour < 17 ? 'afternoon' : 'evening';

  return `Good ${part}, ${session.user?.firstname ?? 'there'}`;
});

const kpis = computed(() => {
  const data = stats.data.value?.data;

  if (!data) {
    return [];
  }

  const sentChange = percentChange(data.this_month.sent, data.last_month.sent);
  const rateChange = data.last_month.sent ? Math.round((data.this_month.delivery_rate - data.last_month.delivery_rate) * 10) / 10 : null;

  return [
    {
      label: 'Messages sent',
      value: formatNumber(data.this_month.sent),
      delta: sentChange === null ? null : `${sentChange >= 0 ? '+' : '−'}${Math.abs(sentChange)}%`,
      trend: sentChange === null ? 'flat' : sentChange >= 0 ? 'up' : 'down',
      note: sentChange === null ? 'this month' : 'vs last month',
    },
    {
      label: 'Delivery rate',
      value: data.this_month.sent ? `${data.this_month.delivery_rate}%` : '–',
      delta: rateChange === null ? null : `${rateChange >= 0 ? '+' : '−'}${Math.abs(rateChange)} pts`,
      trend: rateChange === null ? 'flat' : rateChange >= 0 ? 'up' : 'down',
      note: rateChange === null ? 'of messages with a receipt' : 'vs last month',
    },
    {
      label: 'Contacts',
      value: formatNumber(data.contacts.total),
      delta: `+${formatNumber(data.contacts.added_this_month)}`,
      trend: data.contacts.added_this_month ? 'up' : 'flat',
      note: 'added this month',
    },
    {
      label: 'Opt-outs',
      value: formatNumber(data.contacts.opt_outs_this_month),
      delta: null,
      trend: 'flat',
      note: 'unsubscribes and STOP replies this month',
    },
  ] as const;
});

const setup = computed(() => {
  const list = connections.data.value?.data ?? [];
  const contacts = stats.data.value?.data.contacts.total ?? 0;
  const launched = (campaigns.data.value?.data ?? []).some(campaign => campaign.status !== 'draft');

  return [
    { label: 'Connect an SMS provider', done: list.some(item => item.channel === 'sms'), to: 'providers' },
    { label: 'Connect an email provider', done: list.some(item => item.channel === 'email'), to: 'providers' },
    { label: 'Import your contacts', done: contacts > 0, to: 'contacts.import' },
    { label: 'Send your first campaign', done: launched, to: 'campaigns.new' },
  ];
});

const setupDone = computed(() => setup.value.filter(step => step.done).length);
const loaded = computed(() => !!stats.data.value && !!connections.data.value && !!campaigns.data.value);
</script>

<template>
  <PageHeader :title="greeting" description="Here is how your messages are doing this month.">
    <template #actions>
      <Button variant="secondary" as-child>
        <RouterLink :to="{ name: 'contacts.import' }">
          <Upload />
          Import contacts
        </RouterLink>
      </Button>
      <Button as-child>
        <RouterLink :to="{ name: 'campaigns.new' }">
          <Plus />
          New campaign
        </RouterLink>
      </Button>
    </template>
  </PageHeader>

  <Alert v-if="stats.error.value" variant="destructive" class="flex items-center justify-between gap-3">
    <AlertDescription>We couldn't load your figures. {{ stats.error.value.message }}</AlertDescription>
    <Button variant="secondary" size="sm" class="shrink-0" @click="stats.send()">
      Try again
    </Button>
  </Alert>

  <div class="grid grid-cols-[repeat(auto-fit,minmax(180px,1fr))] gap-4">
    <template v-if="kpis.length">
      <StatCard v-for="kpi in kpis" :key="kpi.label" v-bind="kpi" />
    </template>
    <Skeleton v-for="n in (kpis.length || stats.error.value ? 0 : 4)" v-else :key="n" class="h-[110px] rounded-xl" />
  </div>

  <div class="grid items-start gap-5" :class="setupDone < setup.length || !loaded ? 'xl:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]' : ''">
    <Card>
      <CardHeader>
        <CardTitle>Messages sent per day</CardTitle>
        <CardDescription>Last 14 days</CardDescription>
        <CardAction class="flex gap-3.5 text-xs text-muted-foreground">
          <span class="inline-flex items-center gap-1.5"><i class="inline-block size-2.5 rounded-[3px] bg-brand-grey" />SMS</span>
          <span class="inline-flex items-center gap-1.5"><i class="inline-block size-2.5 rounded-[3px] bg-brand" />Email</span>
        </CardAction>
      </CardHeader>
      <CardContent>
        <VolumeChart v-if="stats.data.value" :days="stats.data.value.data.daily" />
        <Skeleton v-else class="aspect-[72/22] w-full" />
      </CardContent>
    </Card>

    <Card v-if="!loaded || setupDone < setup.length">
      <CardHeader>
        <CardTitle>Finish setting up</CardTitle>
        <CardDescription>{{ setupDone }} of {{ setup.length }} done</CardDescription>
      </CardHeader>
      <CardContent class="grid gap-3">
        <Progress :model-value="setupDone / setup.length * 100" />
        <RouterLink
          v-for="step in setup"
          :key="step.label"
          :to="{ name: step.to }"
          class="flex items-center gap-2 text-inherit no-underline"
        >
          <span class="grid size-[22px] shrink-0 place-items-center rounded-full" :class="step.done ? 'bg-brand text-white' : 'bg-muted'">
            <Check v-if="step.done" class="size-3.5" />
          </span>
          <span class="flex-1 text-[13px]" :class="step.done ? 'text-muted-foreground line-through' : ''">{{ step.label }}</span>
          <ChevronRight v-if="!step.done" class="size-4 text-muted-foreground" />
        </RouterLink>
      </CardContent>
    </Card>
  </div>

  <Card>
    <CardHeader>
      <CardTitle>Recent campaigns</CardTitle>
      <CardAction>
        <Button variant="ghost" size="sm" as-child>
          <RouterLink :to="{ name: 'campaigns' }">
            View all
            <ChevronRight />
          </RouterLink>
        </Button>
      </CardAction>
    </CardHeader>
    <CardContent class="pt-2">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Campaign</TableHead>
            <TableHead>Channel</TableHead>
            <TableHead>Status</TableHead>
            <TableHead class="text-right">
              Recipients
            </TableHead>
            <TableHead>When</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow v-for="campaign in campaigns.data.value?.data ?? []" :key="campaign.id">
            <TableCell class="font-medium">
              {{ campaign.name }}
            </TableCell>
            <TableCell><ChannelLabel :channel="campaign.channel" /></TableCell>
            <TableCell><CampaignStatusBadge :status="campaign.status" /></TableCell>
            <TableCell class="text-right tabular-nums">
              {{ campaign.recipients_count ? formatNumber(campaign.recipients_count) : '–' }}
            </TableCell>
            <TableCell class="text-[13px] text-muted-foreground">
              {{ formatDateTime(campaign.started_at ?? campaign.scheduled_at) || 'Not scheduled' }}
            </TableCell>
          </TableRow>
          <TableEmpty v-if="campaigns.data.value && !campaigns.data.value.data.length" :colspan="5">
            No campaigns yet.
            <RouterLink :to="{ name: 'campaigns.new' }" class="font-medium text-foreground">
              Create your first one
            </RouterLink>
          </TableEmpty>
        </TableBody>
      </Table>
    </CardContent>
  </Card>
</template>
