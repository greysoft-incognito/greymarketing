<script setup lang="ts">
import type { Invitation, Member } from '@/api';
import { invalidateCache } from 'alova';
import { usePagination } from 'alova/client';
import { Crown, Loader2, Mail, MoreHorizontal, Plus, Send, UserMinus, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { team } from '@/api';
import ConfirmDialog from '@/components/app/ConfirmDialog.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import PaginationFooter from '@/components/app/PaginationFooter.vue';
import RefreshButton from '@/components/app/RefreshButton.vue';
import InviteMemberDialog from '@/components/team/InviteMemberDialog.vue';
import TransferOwnershipDialog from '@/components/team/TransferOwnershipDialog.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Avatar } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { NativeSelect } from '@/components/ui/native-select';
import { Progress } from '@/components/ui/progress';
import { Table, TableBody, TableCell, TableEmpty, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formatDay, formatNumber } from '@/lib/format';
import { notifyError, toast } from '@/lib/notify';
import { useWorkspace } from '@/stores/workspace';

const workspace = useWorkspace();
const current = computed(() => workspace.current!);
const id = current.value.id;

const roles = { owner: 'Owner', admin: 'Admin', member: 'Member' } as const;

const {
  data: members,
  page: memberPage,
  pageSize: memberPageSize,
  total: memberTotal,
  pageCount: memberPageCount,
  loading: membersLoading,
  error: membersError,
  refresh: refreshMembers,
} = usePagination(
  (page, limit) => team.members(id, { page, limit }),
  {
    watchingStates: [],
    initialPageSize: 30,
    total: response => response.meta.total,
    data: response => response.data,
  },
);

const {
  data: invitations,
  page: invitePage,
  pageSize: invitePageSize,
  total: inviteTotal,
  pageCount: invitePageCount,
  loading: invitationsLoading,
  error: invitationsError,
  refresh: refreshInvitations,
} = usePagination(
  (page, limit) => team.invitations(id, { page, limit }),
  {
    watchingStates: [],
    immediate: workspace.canManage,
    initialPageSize: 30,
    total: response => response.meta.total,
    data: response => response.data,
  },
);

const seats = computed(() => workspace.billing?.usage.seats ?? null);
const seatsLeft = computed(() => seats.value?.limit == null ? null : Math.max(0, seats.value.limit - seats.value.used));
const seatsFull = computed(() => seatsLeft.value === 0);
const seatsPercent = computed(() => seats.value?.limit ? Math.min(100, Math.round(seats.value.used / seats.value.limit * 100)) : 0);
const loadError = computed(() => membersError.value ?? invitationsError.value);

/**
 * Reload the people, invites and seat count after a change.
 */
async function sync() {
  await Promise.all([
    refreshMembers(),
    workspace.canManage ? refreshInvitations() : null,
    workspace.loadBilling(),
  ]);
}

async function reload() {
  await invalidateCache([team.members(id, { page: memberPage.value, limit: memberPageSize.value }), team.invitations(id, { page: invitePage.value, limit: invitePageSize.value })]);
  await sync();
}

const inviting = ref(false);
const changing = ref<string | null>(null);

async function changeRole(member: Member, role: string | null) {
  if (!role || role === member.role || role === 'owner') {
    return;
  }

  changing.value = member.id;

  try {
    const result = await team.updateRole(id, member.id, role as Invitation['role']);

    toast.success(result.message);
    await refreshMembers();
  } catch (e) {
    notifyError(e);
    await refreshMembers();
  } finally {
    changing.value = null;
  }
}

const removing = ref<Member | null>(null);
const removeOpen = computed({
  get: () => !!removing.value,
  set: (value) => {
    if (!value) {
      removing.value = null;
    }
  },
});

async function remove() {
  try {
    const result = await team.remove(id, removing.value!.id);

    toast.success(result.message);
    await sync();
  } catch (e) {
    notifyError(e);
  }
}

const transferring = ref<Member | null>(null);
const transferOpen = computed({
  get: () => !!transferring.value,
  set: (value) => {
    if (!value) {
      transferring.value = null;
    }
  },
});

/**
 * Reload roles for the signed-in user, then the team, once ownership has moved.
 */
async function transferred() {
  await workspace.load();
  await sync();
}

const busyInvite = ref<string | null>(null);

async function resend(invitation: Invitation) {
  busyInvite.value = invitation.id;

  try {
    toast.success((await team.resend(id, invitation.id)).message);
    await refreshInvitations();
  } catch (e) {
    notifyError(e);
  } finally {
    busyInvite.value = null;
  }
}

async function revoke(invitation: Invitation) {
  busyInvite.value = invitation.id;

  try {
    await team.revoke(id, invitation.id);
    toast.success('Invite revoked. The seat is free again.');
    await sync();
  } catch (e) {
    notifyError(e);
  } finally {
    busyInvite.value = null;
  }
}
</script>

<template>
  <PageHeader title="Team" :description="`People who can use ${current.name}. Everyone sees the same contacts, templates and campaigns.`">
    <template #actions>
      <RefreshButton :refresh="reload" />
      <Button v-if="workspace.canManage" :disabled="seatsFull" @click="inviting = true">
        <Plus />
        Invite member
      </Button>
      <Badge v-else>
        View only · ask an admin to invite people
      </Badge>
    </template>
  </PageHeader>

  <div class="grid max-w-[880px] gap-5">
    <Alert v-if="loadError" variant="destructive" class="flex items-center justify-between gap-3">
      <AlertDescription>We couldn't load your team. {{ loadError.message }}</AlertDescription>
      <Button variant="secondary" size="sm" class="shrink-0" @click="reload">
        Try again
      </Button>
    </Alert>

    <Alert v-if="workspace.canManage && seatsFull" variant="warning">
      <AlertTitle>All {{ seats?.limit }} seats on the {{ workspace.billing?.plan?.name }} plan are in use</AlertTitle>
      <AlertDescription class="text-muted-foreground">
        <p>
          Remove someone or revoke an invite to free a seat, or
          <RouterLink :to="{ name: 'billing' }" class="text-foreground underline underline-offset-2">
            upgrade your plan
          </RouterLink>
          for more.
        </p>
      </AlertDescription>
    </Alert>

    <Card v-if="seats" class="grid gap-2 px-5 py-[18px]">
      <div class="flex items-center justify-between gap-3 text-sm">
        <span class="font-medium">Team seats</span>
        <span class="text-muted-foreground tabular-nums">
          {{ formatNumber(seats.used) }} {{ seats.limit === null ? 'used · unlimited' : `of ${formatNumber(seats.limit)} used` }}
        </span>
      </div>
      <Progress v-if="seats.limit !== null" :model-value="seatsPercent" :class="seatsFull && '[&>[data-slot=progress-indicator]]:bg-warning'" />
      <span class="text-xs text-muted-foreground">
        Pending invites hold a seat until they are accepted, revoked or expire.
      </span>
    </Card>

    <Card>
      <CardHeader>
        <CardTitle>Members</CardTitle>
        <CardDescription>Owners and admins manage providers, settings, billing and the team. Members run contacts, templates and campaigns.</CardDescription>
      </CardHeader>
      <CardContent class="px-2 pt-0">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead class="pl-4">
                Name
              </TableHead>
              <TableHead>Role</TableHead>
              <TableHead class="hidden sm:table-cell">
                Joined
              </TableHead>
              <TableHead class="w-10 sm:w-48" />
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableEmpty v-if="membersLoading && !members.length" :colspan="4">
              <Loader2 class="mx-auto size-4 animate-spin text-muted-foreground" />
            </TableEmpty>
            <TableRow v-for="member in members" :key="member.id">
              <TableCell class="pl-4">
                <div class="flex min-w-0 items-center gap-3">
                  <Avatar :name="member.name" class="max-sm:hidden" />
                  <div class="grid min-w-0">
                    <span class="truncate font-medium">
                      {{ member.name }}
                      <span v-if="member.is_you" class="text-xs font-normal text-muted-foreground">(you)</span>
                    </span>
                    <span class="truncate text-xs text-muted-foreground">{{ member.email }}</span>
                  </div>
                </div>
              </TableCell>
              <TableCell>
                <div v-if="workspace.canManage && member.role !== 'owner'" class="flex items-center gap-2">
                  <NativeSelect
                    :model-value="member.role"
                    :aria-label="`Role for ${member.name}`"
                    :disabled="changing === member.id"
                    class="w-[108px]"
                    @update:model-value="changeRole(member, $event ?? null)"
                  >
                    <option value="admin">
                      Admin
                    </option>
                    <option value="member">
                      Member
                    </option>
                  </NativeSelect>
                  <Loader2 v-if="changing === member.id" class="size-3.5 animate-spin text-muted-foreground" />
                </div>
                <Badge v-else :variant="member.role === 'owner' ? 'brand' : 'default'">
                  {{ roles[member.role] }}
                </Badge>
              </TableCell>
              <TableCell class="hidden text-muted-foreground tabular-nums sm:table-cell">
                {{ formatDay(member.joined_at) }}
              </TableCell>
              <TableCell class="text-right">
                <template v-if="workspace.canManage && member.role !== 'owner' && !member.is_you">
                  <div class="hidden justify-end gap-1 sm:flex">
                    <Button v-if="current.is_owner" variant="ghost" size="sm" @click="transferring = member">
                      Make owner
                    </Button>
                    <Button variant="ghost" size="sm" @click="removing = member">
                      Remove
                    </Button>
                  </div>
                  <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                      <Button variant="ghost" size="icon" class="ml-auto flex size-8 sm:hidden" :aria-label="`${member.name} actions`">
                        <MoreHorizontal />
                      </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-40">
                      <DropdownMenuItem v-if="current.is_owner" @select="transferring = member">
                        <Crown />
                        Make owner
                      </DropdownMenuItem>
                      <DropdownMenuItem variant="destructive" @select="removing = member">
                        <UserMinus />
                        Remove
                      </DropdownMenuItem>
                    </DropdownMenuContent>
                  </DropdownMenu>
                </template>
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </CardContent>
      <PaginationFooter v-model="memberPage" :page-size="memberPageSize" :total="memberTotal" :page-count="memberPageCount" noun="members" />
      <p v-if="!workspace.canManage && !current.is_owner" class="px-5 pb-5 text-xs text-muted-foreground">
        To leave {{ current.name }}, go to
        <RouterLink :to="{ name: 'settings' }" class="text-foreground underline underline-offset-2">
          Workspace settings
        </RouterLink>.
      </p>
    </Card>

    <Card v-if="workspace.canManage">
      <CardHeader>
        <CardTitle>Pending invites</CardTitle>
        <CardDescription>Invites are emailed with a link that works for 7 days.</CardDescription>
      </CardHeader>
      <CardContent class="px-2 pt-0">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead class="pl-4">
                Email
              </TableHead>
              <TableHead>Role</TableHead>
              <TableHead class="hidden sm:table-cell">
                Status
              </TableHead>
              <TableHead class="w-10 sm:w-40" />
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableEmpty v-if="invitationsLoading && !invitations.length" :colspan="4">
              <Loader2 class="mx-auto size-4 animate-spin text-muted-foreground" />
            </TableEmpty>
            <TableEmpty v-else-if="!invitations.length" :colspan="4">
              No pending invites.
            </TableEmpty>
            <TableRow v-for="invitation in invitations" :key="invitation.id">
              <TableCell class="pl-4">
                <div class="flex min-w-0 items-center gap-3">
                  <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-muted text-muted-foreground max-sm:hidden">
                    <Mail class="size-4" />
                  </span>
                  <div class="grid min-w-0">
                    <span class="truncate font-medium">{{ invitation.email }}</span>
                    <span class="truncate text-xs text-muted-foreground">
                      {{ invitation.invited_by ? `Invited by ${invitation.invited_by.name} · ` : '' }}expires {{ formatDay(invitation.expires_at) }}
                    </span>
                  </div>
                </div>
              </TableCell>
              <TableCell>
                <Badge>{{ roles[invitation.role] }}</Badge>
              </TableCell>
              <TableCell class="hidden sm:table-cell">
                <Badge variant="warning">
                  Pending
                </Badge>
              </TableCell>
              <TableCell>
                <div class="hidden justify-end gap-1 sm:flex">
                  <Button variant="ghost" size="sm" :disabled="busyInvite === invitation.id" @click="resend(invitation)">
                    Resend
                  </Button>
                  <Button variant="ghost" size="sm" :disabled="busyInvite === invitation.id" @click="revoke(invitation)">
                    Revoke
                  </Button>
                </div>
                <DropdownMenu>
                  <DropdownMenuTrigger as-child>
                    <Button variant="ghost" size="icon" class="ml-auto flex size-8 sm:hidden" :disabled="busyInvite === invitation.id" :aria-label="`Invite for ${invitation.email} actions`">
                      <MoreHorizontal />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent align="end" class="w-40">
                    <DropdownMenuItem @select="resend(invitation)">
                      <Send />
                      Resend
                    </DropdownMenuItem>
                    <DropdownMenuItem variant="destructive" @select="revoke(invitation)">
                      <X />
                      Revoke
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </CardContent>
      <PaginationFooter v-model="invitePage" :page-size="invitePageSize" :total="inviteTotal" :page-count="invitePageCount" noun="invites" />
    </Card>
  </div>

  <InviteMemberDialog v-model:open="inviting" :seats-left="seatsLeft" @sent="sync" />

  <TransferOwnershipDialog v-model:open="transferOpen" :member="transferring" @transferred="transferred" />

  <ConfirmDialog
    v-model:open="removeOpen"
    :title="`Remove ${removing?.name ?? ''}?`"
    :description="`They lose access to ${current.name} straight away. Campaigns they made stay. You can invite them again later.`"
    action="Remove"
    :run="remove"
  />
</template>
