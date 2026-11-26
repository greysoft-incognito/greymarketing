<script setup lang="ts">
import type { Channel, Contact, ContactList } from '@/api';
import { useRequest, useWatcher } from 'alova/client';
import { ListPlus, ListX, MoreHorizontal, Pencil, Plus, Search, Trash2, Upload, Users } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { contacts, lists } from '@/api';
import ConfirmDialog from '@/components/app/ConfirmDialog.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import PaginationFooter from '@/components/app/PaginationFooter.vue';
import ContactDialog from '@/components/contacts/ContactDialog.vue';
import ListDialog from '@/components/contacts/ListDialog.vue';
import SubscriptionBadge from '@/components/contacts/SubscriptionBadge.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Avatar } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableEmpty, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { contactName, formatDay, formatNumber } from '@/lib/format';
import { notifyError, toast } from '@/lib/notify';
import { cn } from '@/lib/utils';
import { useWorkspace } from '@/stores/workspace';

const workspace = useWorkspace();
const id = workspace.current!.id;

const search = ref('');
const listId = ref<string | null>(null);
const subscribed = ref<Channel | ''>('');
const page = ref(1);
const selected = ref<string[]>([]);

const listQuery = useRequest(() => lists.list(id));
const totals = useRequest(() => contacts.list(id, { limit: 1 }));
const query = useWatcher(
  () => contacts.list(id, {
    page: page.value,
    ...(search.value.trim() ? { search: search.value.trim() } : {}),
    ...(listId.value ? { list: listId.value } : {}),
    ...(subscribed.value ? { subscribed: subscribed.value } : {}),
  }),
  [search, listId, subscribed, page],
  { immediate: true, debounce: [300, 0, 0, 0] },
);

watch([search, listId, subscribed], () => {
  page.value = 1;
});

watch(() => query.data.value, () => {
  selected.value = [];
});

const allLists = computed(() => listQuery.data.value?.data ?? []);
const rows = computed(() => query.data.value?.data ?? []);
const total = computed(() => totals.data.value?.meta.total ?? 0);
const activeList = computed(() => allLists.value.find(list => list.id === listId.value) ?? null);
const filtering = computed(() => !!search.value.trim() || !!subscribed.value);

const allSelected = computed<boolean | 'indeterminate'>(() => {
  if (!rows.value.length || !selected.value.length) {
    return false;
  }

  return selected.value.length === rows.value.length ? true : 'indeterminate';
});

function selectAll(checked: boolean | 'indeterminate') {
  selected.value = checked === true ? rows.value.map(row => row.id) : [];
}

function selectRow(contactId: string, checked: boolean | 'indeterminate') {
  selected.value = checked === true
    ? [...selected.value, contactId]
    : selected.value.filter(item => item !== contactId);
}

async function refresh() {
  await Promise.all([query.send(), totals.send(), listQuery.send()]);
}

const contactOpen = ref(false);
const editingContact = ref<Contact | null>(null);

function openContact(contact: Contact | null = null) {
  editingContact.value = contact;
  contactOpen.value = true;
}

const listOpen = ref(false);
const editingList = ref<ContactList | null>(null);

function openList(list: ContactList | null = null) {
  editingList.value = list;
  listOpen.value = true;
}

async function listSaved(list: ContactList) {
  await listQuery.send();

  if (!editingList.value) {
    listId.value = list.id;
  }
}

const confirm = ref<{ title: string; description: string; action: string; run: () => Promise<unknown> } | null>(null);
const confirmOpen = ref(false);

function ask(options: NonNullable<typeof confirm.value>) {
  confirm.value = options;
  confirmOpen.value = true;
}

function removeContact(contact: Contact) {
  ask({
    title: `Delete ${contactName(contact)}?`,
    description: 'They are removed from every list. Past campaign reports keep their delivery records.',
    action: 'Delete contact',
    run: async () => {
      try {
        await contacts.remove(id, contact.id);
        toast.success('Contact deleted.');
        await refresh();
      } catch (e) {
        notifyError(e);
      }
    },
  });
}

function removeList(list: ContactList) {
  ask({
    title: `Delete ${list.name}?`,
    description: 'Only the list is deleted. The contacts on it stay in your workspace.',
    action: 'Delete list',
    run: async () => {
      try {
        await lists.remove(id, list.id);
        toast.success('List deleted.');
        listId.value = listId.value === list.id ? null : listId.value;
        await listQuery.send();
      } catch (e) {
        notifyError(e);
      }
    },
  });
}

async function addSelectedTo(list: ContactList) {
  try {
    await lists.attach(id, list.id, selected.value);
    toast.success(`Added ${selected.value.length} to ${list.name}.`);
    await Promise.all([query.send(), listQuery.send()]);
  } catch (e) {
    notifyError(e);
  }
}

async function removeSelectedFromList() {
  const list = activeList.value!;

  try {
    await lists.detach(id, list.id, selected.value);
    toast.success(`Removed ${selected.value.length} from ${list.name}.`);
    await Promise.all([query.send(), listQuery.send()]);
  } catch (e) {
    notifyError(e);
  }
}
</script>

<template>
  <PageHeader
    title="Contacts"
    :description="totals.data.value ? `${formatNumber(total)} ${total === 1 ? 'person' : 'people'} across ${allLists.length} ${allLists.length === 1 ? 'list' : 'lists'}. Lists can overlap.` : undefined"
  >
    <template #actions>
      <Button variant="secondary" @click="openList()">
        <ListPlus />
        New list
      </Button>
      <Button variant="secondary" as-child>
        <RouterLink :to="{ name: 'contacts.import' }">
          <Upload />
          Import CSV
        </RouterLink>
      </Button>
      <Button @click="openContact()">
        <Plus />
        Add contact
      </Button>
    </template>
  </PageHeader>

  <div class="grid grid-cols-2 gap-3 xl:grid-cols-4">
    <button
      type="button"
      :class="cn('grid cursor-pointer gap-1 rounded-xl p-4 text-left shadow-card transition-colors', !listId ? 'bg-brand-soft' : 'bg-card hover:bg-muted/40')"
      :aria-pressed="!listId"
      @click="listId = null"
    >
      <span class="flex items-center justify-between gap-2">
        <span class="font-semibold">All contacts</span>
        <Users class="size-4 text-muted-foreground" />
      </span>
      <span class="text-xl font-semibold tabular-nums">{{ formatNumber(total) }}</span>
      <span class="text-xs text-muted-foreground">Everyone in this workspace</span>
    </button>

    <template v-if="listQuery.data.value">
      <div
        v-for="list in allLists"
        :key="list.id"
        :class="cn('relative rounded-xl shadow-card transition-colors', listId === list.id ? 'bg-brand-soft' : 'bg-card hover:bg-muted/40')"
      >
        <button
          type="button"
          class="grid w-full cursor-pointer gap-1 p-4 pr-12 text-left"
          :aria-pressed="listId === list.id"
          @click="listId = list.id"
        >
          <span class="truncate font-semibold">{{ list.name }}</span>
          <span class="text-xl font-semibold tabular-nums">{{ formatNumber(list.contacts_count ?? 0) }}</span>
          <span class="truncate text-xs text-muted-foreground">{{ list.description || 'List' }}</span>
        </button>
        <DropdownMenu>
          <DropdownMenuTrigger as-child>
            <Button variant="ghost" size="icon" class="absolute top-3 right-3 size-7" :aria-label="`${list.name} actions`">
              <MoreHorizontal />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end" class="w-44">
            <DropdownMenuItem @select="openList(list)">
              <Pencil />
              Rename
            </DropdownMenuItem>
            <DropdownMenuItem variant="destructive" @select="removeList(list)">
              <Trash2 />
              Delete list
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </div>
    </template>
    <Skeleton v-for="n in (listQuery.data.value ? 0 : 3)" v-else :key="n" class="h-[104px] rounded-xl" />

  </div>

  <Alert v-if="query.error.value" variant="destructive">
    <AlertDescription>We couldn't load contacts. {{ query.error.value.message }}</AlertDescription>
  </Alert>

  <Card>
    <CardHeader class="flex flex-wrap items-center gap-3">
      <CardTitle class="mr-auto">
        {{ activeList?.name ?? 'All contacts' }}
      </CardTitle>
      <div class="flex w-full flex-wrap gap-2 sm:w-auto">
        <div class="relative min-w-0 basis-full sm:w-[240px] sm:basis-auto">
          <Search class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
          <Input v-model="search" class="pl-9" placeholder="Name, email or phone" aria-label="Search contacts" />
        </div>
        <NativeSelect v-model="subscribed" class="w-[170px]" aria-label="Subscription">
          <option value="">
            Any subscription
          </option>
          <option value="email">
            Subscribed to email
          </option>
          <option value="sms">
            Subscribed to SMS
          </option>
        </NativeSelect>
      </div>
    </CardHeader>

    <div v-if="selected.length" class="mx-5 mt-3 flex flex-wrap items-center gap-2 rounded-lg bg-brand-soft px-3 py-2 text-sm">
      <span class="mr-auto font-medium text-brand">{{ selected.length }} selected</span>
      <DropdownMenu>
        <DropdownMenuTrigger as-child>
          <Button variant="secondary" size="sm" :disabled="!allLists.length">
            <ListPlus />
            Add to list
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="max-h-72 w-56 overflow-y-auto">
          <DropdownMenuLabel>Add to</DropdownMenuLabel>
          <DropdownMenuSeparator />
          <DropdownMenuItem v-for="list in allLists" :key="list.id" @select="addSelectedTo(list)">
            {{ list.name }}
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
      <Button v-if="activeList" variant="secondary" size="sm" @click="removeSelectedFromList">
        <ListX />
        Remove from {{ activeList.name }}
      </Button>
    </div>

    <CardContent class="px-2 pt-2">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead class="w-10 pl-4">
              <Checkbox :model-value="allSelected" :disabled="!rows.length" aria-label="Select all on this page" @update:model-value="selectAll" />
            </TableHead>
            <TableHead>Name</TableHead>
            <TableHead>Phone</TableHead>
            <TableHead>Email</TableHead>
            <TableHead>Lists</TableHead>
            <TableHead>SMS</TableHead>
            <TableHead>Email</TableHead>
            <TableHead>Added</TableHead>
            <TableHead class="w-10" />
          </TableRow>
        </TableHeader>
        <TableBody>
          <template v-if="query.data.value">
            <TableRow v-for="contact in rows" :key="contact.id" :data-state="selected.includes(contact.id) ? 'selected' : undefined">
              <TableCell class="pl-4">
                <Checkbox
                  :model-value="selected.includes(contact.id)"
                  :aria-label="`Select ${contactName(contact)}`"
                  @update:model-value="selectRow(contact.id, $event)"
                />
              </TableCell>
              <TableCell>
                <button type="button" class="flex cursor-pointer items-center gap-2.5 text-left" @click="openContact(contact)">
                  <Avatar :name="contactName(contact)" class="size-7" />
                  <span class="font-medium whitespace-nowrap">{{ contactName(contact) }}</span>
                </button>
              </TableCell>
              <TableCell class="font-mono text-[13px] whitespace-nowrap">
                {{ contact.phone ?? '–' }}
              </TableCell>
              <TableCell class="text-[13px]">
                {{ contact.email ?? '–' }}
              </TableCell>
              <TableCell class="max-w-[200px] truncate text-[13px] text-muted-foreground">
                {{ contact.lists?.map(list => list.name).join(', ') || '–' }}
              </TableCell>
              <TableCell><SubscriptionBadge :reachable="!!contact.phone" :subscribed="contact.sms_subscribed" /></TableCell>
              <TableCell><SubscriptionBadge :reachable="!!contact.email" :subscribed="contact.email_subscribed" /></TableCell>
              <TableCell class="text-[13px] whitespace-nowrap text-muted-foreground">
                {{ formatDay(contact.created_at) }}
              </TableCell>
              <TableCell>
                <DropdownMenu>
                  <DropdownMenuTrigger as-child>
                    <Button variant="ghost" size="icon" class="size-8" :aria-label="`${contactName(contact)} actions`">
                      <MoreHorizontal />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent align="end" class="w-44">
                    <DropdownMenuItem @select="openContact(contact)">
                      <Pencil />
                      Edit
                    </DropdownMenuItem>
                    <DropdownMenuItem variant="destructive" @select="removeContact(contact)">
                      <Trash2 />
                      Delete
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
              </TableCell>
            </TableRow>
            <TableEmpty v-if="!rows.length" :colspan="9">
              <template v-if="filtering">
                No contacts match these filters.
              </template>
              <template v-else-if="activeList">
                {{ activeList.name }} is empty. Select contacts under All contacts and use Add to list.
              </template>
              <template v-else>
                No contacts yet.
                <RouterLink :to="{ name: 'contacts.import' }" class="font-medium text-foreground">
                  Import a CSV
                </RouterLink>
                or add one by hand.
              </template>
            </TableEmpty>
          </template>
          <TableRow v-for="n in (query.data.value ? 0 : 6)" v-else :key="n">
            <TableCell :colspan="9">
              <Skeleton class="h-6 w-full" />
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </CardContent>

    <PaginationFooter v-model="page" :meta="query.data.value?.meta" noun="contacts" />
  </Card>

  <ContactDialog v-model:open="contactOpen" :contact="editingContact" :lists="allLists" :default-list="listId" @saved="refresh" />
  <ListDialog v-model:open="listOpen" :list="editingList" @saved="listSaved" />
  <ConfirmDialog
    v-if="confirm"
    v-model:open="confirmOpen"
    :title="confirm.title"
    :description="confirm.description"
    :action="confirm.action"
    :run="confirm.run"
  />
</template>
