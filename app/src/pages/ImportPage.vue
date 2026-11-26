<script setup lang="ts">
import type { ContactImport, ImportUpload } from '@/api';
import { usePagination, useRequest } from 'alova/client';
import { CircleCheck, Download, FileSpreadsheet, Loader2, TriangleAlert, Upload } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { forget, imports, lists } from '@/api';
import FormField from '@/components/app/FormField.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import PaginationFooter from '@/components/app/PaginationFooter.vue';
import RefreshButton from '@/components/app/RefreshButton.vue';
import StatCard from '@/components/app/StatCard.vue';
import ImportSteps from '@/components/contacts/ImportSteps.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardAction, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Progress } from '@/components/ui/progress';
import { Table, TableBody, TableCell, TableEmpty, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { downloadCsv } from '@/lib/csv';
import { formatDateTime, formatNumber } from '@/lib/format';
import { notifyError, toast } from '@/lib/notify';
import { cn } from '@/lib/utils';
import { useWorkspace } from '@/stores/workspace';

const workspace = useWorkspace();
const route = useRoute();
const router = useRouter();
const id = workspace.current!.id;

const NEW_LIST = '__new__';
const SKIP = '';

const upload = ref<ImportUpload | null>(null);
const fileName = ref('');
const mapping = ref<Record<string, string>>({});
const listChoice = ref<string>(SKIP);
const newListName = ref('');
const current = ref<ContactImport | null>(null);
const busy = ref<'uploading' | 'starting' | null>(null);
const dragging = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);

const { data: listData } = useRequest(() => lists.list(id));
const {
  data: history,
  page: historyPage,
  pageSize: historyPageSize,
  total: historyTotal,
  pageCount: historyPageCount,
  loading: historyLoading,
  refresh: refreshHistory,
} = usePagination((pageNumber, perPage) => imports.list(id, pageNumber, perPage), {
  initialPageSize: 10,
  total: response => response.meta.total,
  data: response => response.data,
});

const step = computed(() => (current.value ? 3 : upload.value ? 2 : 1));


/**
 * A custom field name built from a column header, matching what the API accepts.
 */
function fieldName(header: string) {
  return header.trim().toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '') || 'field';
}

function sampleFor(header: string) {
  return upload.value?.sample.map(row => row[header]).find(value => value?.trim()) ?? '';
}

const options = computed(() => [
  { value: 'email', label: 'Email' },
  { value: 'phone', label: 'Phone' },
  { value: 'first_name', label: 'First name' },
  { value: 'last_name', label: 'Last name' },
]);

const mapped = computed(() => Object.values(mapping.value).filter(Boolean));
const mappingError = computed(() => {
  if (!mapped.value.includes('email') && !mapped.value.includes('phone')) {
    return 'Map at least one column to Email or Phone.';
  }

  const duplicate = mapped.value.find((value, index) => mapped.value.indexOf(value) !== index);

  return duplicate ? `More than one column is saved as ${duplicate.replace('fields.', '')}. Pick one.` : null;
});

async function choose(file: File | undefined) {
  if (!file) {
    return;
  }

  if (!/\.(csv|txt|vcf)$/i.test(file.name)) {
    toast.error('Choose a .csv or .vcf file.');

    return;
  }

  busy.value = 'uploading';
  fileName.value = file.name;

  try {
    upload.value = await imports.upload(id, file);
    mapping.value = Object.fromEntries(upload.value.headers.map(header => [header, upload.value!.data.mapping[header] ?? SKIP]));
  } catch (e) {
    notifyError(e);
  } finally {
    busy.value = null;

    if (fileInput.value) {
      fileInput.value.value = '';
    }
  }
}

function drop(event: DragEvent) {
  dragging.value = false;
  choose(event.dataTransfer?.files[0]);
}

async function start() {
  busy.value = 'starting';

  try {
    let list: string | null = listChoice.value === SKIP ? null : listChoice.value;

    if (list === NEW_LIST) {
      list = (await lists.create(id, { name: newListName.value.trim() })).data.id;
    }

    const result = await imports.start(id, upload.value!.data.id, {
      mapping: Object.fromEntries(Object.entries(mapping.value).map(([header, field]) => [header, field || null])),
      list,
    });

    current.value = result.data;
    watching = true;
    router.replace({ query: { import: result.data.id } });
    poll();
  } catch (e) {
    notifyError(e);
  } finally {
    busy.value = null;
  }
}

let timer: ReturnType<typeof setTimeout> | undefined;
let watching = false;

/**
 * Check on a running import until it finishes.
 */
function poll() {
  clearTimeout(timer);

  if (!current.value || !['pending', 'processing'].includes(current.value.status)) {
    if (watching) {
      watching = false;
      finished();
    }

    return;
  }

  watching = true;
  timer = setTimeout(async () => {
    try {
      current.value = (await imports.show(id, current.value!.id)).data;
    } catch {
      // Keep the last known state and try again.
    }

    poll();
  }, 2000);
}

onBeforeUnmount(() => clearTimeout(timer));

/**
 * The import adds contacts after the start request returns, so anything cached before it finished is stale.
 */
async function finished() {
  await forget('contacts.list', 'lists.list', 'imports.list', 'stats', 'billing');
  await Promise.all([refreshHistory(), workspace.loadBilling()]);
}

async function open(importId: string) {
  try {
    current.value = (await imports.show(id, importId)).data;
    upload.value = null;
    poll();
  } catch (e) {
    notifyError(e);
    router.replace({ query: {} });
  }
}

watch(() => route.query.import, (value) => {
  if (typeof value === 'string' && value !== current.value?.id) {
    open(value);
  } else if (!value) {
    reset();
  }
}, { immediate: true });

function reset() {
  clearTimeout(timer);
  upload.value = null;
  current.value = null;
  listChoice.value = SKIP;
  newListName.value = '';

  if (route.query.import) {
    router.replace({ query: {} });
  }
}

function sampleCsv() {
  downloadCsv('greymarketing-sample.csv', [
    ['first_name', 'last_name', 'phone', 'email', 'city'],
    ['Tunde', 'Bakare', '08034120098', 'tunde@example.com', 'Lagos'],
    ['Chiamaka', 'Eze', '+2348123307714', 'chiamaka@example.com', 'Enugu'],
  ]);
}

function skippedCsv() {
  downloadCsv(`skipped-rows-${current.value!.id}.csv`, [
    ['line', 'reason'],
    ...current.value!.failures.map(failure => [failure.line, failure.reason]),
  ]);
}

const running = computed(() => !!current.value && ['pending', 'processing'].includes(current.value.status));

const statusBadge = (status: ContactImport['status']) => ({
  pending: { label: 'Not started', variant: 'default' },
  processing: { label: 'Importing', variant: 'info' },
  completed: { label: 'Done', variant: 'success' },
  failed: { label: 'Failed', variant: 'destructive' },
} as const)[status];
</script>

<template>
  <PageHeader title="Import contacts" description="Upload a CSV or vCard file, tell us what each column is, and we add or update your contacts." />

  <ImportSteps :steps="['Upload file', 'Map columns', 'Import']" :current="step === 3 && !running ? 4 : step" />

  <!-- Upload -->
  <Card v-if="step === 1">
    <CardContent class="grid gap-4 py-6">
      <label
        :class="cn(
          'grid cursor-pointer place-items-center gap-2 rounded-xl px-6 py-12 text-center transition-colors',
          dragging ? 'bg-brand-soft' : 'bg-muted/60 hover:bg-muted',
        )"
        @dragover.prevent="dragging = true"
        @dragleave.prevent="dragging = false"
        @drop.prevent="drop"
      >
        <span class="grid size-11 place-items-center rounded-full bg-card text-brand shadow-card">
          <Loader2 v-if="busy === 'uploading'" class="size-5 animate-spin" />
          <Upload v-else class="size-5" />
        </span>
        <span class="font-semibold">{{ busy === 'uploading' ? `Reading ${fileName}…` : 'Drop a CSV or vCard file here' }}</span>
        <span class="text-sm text-muted-foreground">or <u>browse your computer</u> · up to 20 MB</span>
        <input ref="fileInput" type="file" accept=".csv,.vcf,text/csv,text/vcard,text/x-vcard" class="sr-only" :disabled="!!busy" @change="choose(($event.target as HTMLInputElement).files?.[0])">
      </label>
      <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground">
        <span>A CSV's first row must be column names. vCards exported from a phone or Google Contacts work as they are. We recognise email, phone, first and last name; anything else can be a custom field.</span>
        <Button variant="ghost" size="sm" @click="sampleCsv">
          <Download />
          Sample CSV
        </Button>
      </div>
    </CardContent>
  </Card>

  <!-- Map columns -->
  <Card v-if="step === 2 && upload">
    <CardHeader>
      <CardTitle>Map columns</CardTitle>
      <CardDescription class="flex items-center gap-1.5">
        <FileSpreadsheet class="size-3.5" />
        <span class="font-mono">{{ fileName }}</span> · {{ upload.headers.length }} columns
      </CardDescription>
    </CardHeader>
    <CardContent class="grid gap-5 px-2 pt-2">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Column in your file</TableHead>
            <TableHead class="hidden sm:table-cell">
              Sample value
            </TableHead>
            <TableHead>Save as</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow v-for="header in upload.headers" :key="header">
            <TableCell class="max-w-[160px] font-medium sm:max-w-none">
              {{ header || '(no name)' }}
              <span class="block truncate font-mono text-xs font-normal text-muted-foreground sm:hidden">{{ sampleFor(header) || '–' }}</span>
            </TableCell>
            <TableCell class="hidden max-w-[220px] truncate sm:table-cell font-mono text-[13px] text-muted-foreground">
              {{ sampleFor(header) || '–' }}
            </TableCell>
            <TableCell>
              <NativeSelect v-model="mapping[header]" class="w-[180px] sm:w-[240px]" :aria-label="`Save ${header} as`">
                <option v-for="option in options" :key="option.value" :value="option.value">
                  {{ option.label }}
                </option>
                <option :value="`fields.${fieldName(header)}`">
                  Custom field: {{ fieldName(header) }}
                </option>
                <option :value="SKIP">
                  Don't import
                </option>
              </NativeSelect>
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>

      <div class="grid gap-4 px-4 sm:grid-cols-2">
        <FormField id="import-list" label="Add everyone to a list" hint="Optional. Contacts already on other lists stay there.">
          <NativeSelect id="import-list" v-model="listChoice">
            <option :value="SKIP">
              Don't add to a list
            </option>
            <option v-for="list in listData?.data ?? []" :key="list.id" :value="list.id">
              {{ list.name }}
            </option>
            <option :value="NEW_LIST">
              + Create a new list
            </option>
          </NativeSelect>
        </FormField>
        <FormField v-if="listChoice === NEW_LIST" id="import-new-list" label="New list name">
          <Input id="import-new-list" v-model="newListName" placeholder="November import" />
        </FormField>
      </div>

      <Alert v-if="mappingError" variant="warning" class="mx-4 w-auto">
        <TriangleAlert />
        <AlertDescription>{{ mappingError }}</AlertDescription>
      </Alert>
      <p v-else class="px-4 text-xs text-muted-foreground">
        Rows that match an existing email or phone update that contact. Phone numbers without a country code use your workspace country.
      </p>
    </CardContent>
    <CardFooter class="justify-end gap-2">
      <Button variant="secondary" :disabled="!!busy" @click="reset">
        Choose another file
      </Button>
      <Button :disabled="!!busy || !!mappingError || (listChoice === NEW_LIST && !newListName.trim())" @click="start">
        <Loader2 v-if="busy === 'starting'" class="animate-spin" />
        Start import
      </Button>
    </CardFooter>
  </Card>

  <!-- Progress and result -->
  <template v-if="step === 3 && current">
    <Card>
      <CardContent class="grid gap-4 py-6">
        <div class="flex items-center gap-3">
          <Loader2 v-if="running" class="size-5 animate-spin text-brand" />
          <CircleCheck v-else-if="current.status === 'completed'" class="size-5 text-success" />
          <TriangleAlert v-else class="size-5 text-destructive" />
          <div class="grid">
            <span class="font-semibold">
              {{ running ? 'Importing your contacts…' : current.status === 'completed' ? 'Import finished' : 'Import stopped' }}
            </span>
            <span class="text-xs text-muted-foreground">
              {{ running ? 'You can leave this page. The import keeps going.' : formatDateTime(current.completed_at ?? current.created_at) }}
              <template v-if="current.list"> · Added to {{ current.list.name }}</template>
            </span>
          </div>
        </div>
        <Progress v-if="running" :model-value="100" class="animate-pulse" />
      </CardContent>
    </Card>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
      <StatCard label="Rows read" :value="formatNumber(current.processed)" :delta="null" trend="flat" note="from your file" />
      <StatCard label="Added or updated" :value="formatNumber(current.imported)" :delta="null" trend="flat" note="contacts saved" />
      <StatCard label="Skipped" :value="formatNumber(current.skipped)" :delta="null" trend="flat" note="rows not saved" />
    </div>

    <Card v-if="current.failures.length">
      <CardHeader>
        <CardTitle>Skipped rows</CardTitle>
        <CardDescription>Rows are lines in a CSV, or contacts in order in a vCard. Fix these in your file and import it again. Rows already saved are updated, not duplicated.</CardDescription>
      </CardHeader>
      <CardContent class="px-2 pt-2">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead class="w-24">
                Row
              </TableHead>
              <TableHead>Reason</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="failure in current.failures.slice(0, 50)" :key="failure.line">
              <TableCell class="font-mono text-[13px]">
                {{ failure.line || '–' }}
              </TableCell>
              <TableCell class="text-[13px]">
                {{ failure.reason }}
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </CardContent>
      <CardFooter class="justify-between gap-2">
        <span class="text-xs text-muted-foreground">
          {{ current.failures.length > 50 ? `Showing 50 of ${formatNumber(current.failures.length)}` : '' }}
        </span>
        <Button variant="secondary" size="sm" @click="skippedCsv">
          <Download />
          Download skipped rows
        </Button>
      </CardFooter>
    </Card>

    <div v-if="!running" class="flex flex-wrap justify-end gap-2">
      <Button variant="secondary" @click="reset">
        Import another file
      </Button>
      <Button as-child>
        <RouterLink :to="{ name: 'contacts' }">
          View contacts
        </RouterLink>
      </Button>
    </div>
  </template>

  <Card v-if="step === 1">
    <CardHeader>
      <CardTitle>Recent imports</CardTitle>
      <CardAction>
        <RefreshButton :refresh="refreshHistory" />
      </CardAction>
    </CardHeader>
    <CardContent class="px-2 pt-2">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Started</TableHead>
            <TableHead>Status</TableHead>
            <TableHead>List</TableHead>
            <TableHead class="text-right">
              Saved
            </TableHead>
            <TableHead class="text-right">
              Skipped
            </TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow
            v-for="item in history"
            :key="item.id"
            :class="item.status !== 'pending' && 'cursor-pointer'"
            @click="item.status !== 'pending' && router.replace({ query: { import: item.id } })"
          >
            <TableCell class="text-[13px] whitespace-nowrap">
              {{ formatDateTime(item.created_at) }}
            </TableCell>
            <TableCell>
              <Badge :variant="statusBadge(item.status).variant">
                {{ statusBadge(item.status).label }}
              </Badge>
            </TableCell>
            <TableCell class="text-[13px] text-muted-foreground">
              {{ item.list?.name ?? '–' }}
            </TableCell>
            <TableCell class="text-right tabular-nums">
              {{ formatNumber(item.imported) }}
            </TableCell>
            <TableCell class="text-right tabular-nums">
              {{ formatNumber(item.skipped) }}
            </TableCell>
          </TableRow>
          <TableEmpty v-if="!historyLoading && !history.length" :colspan="5">
            No imports yet.
          </TableEmpty>
        </TableBody>
      </Table>
    </CardContent>
    <PaginationFooter v-model="historyPage" :page-size="historyPageSize" :total="historyTotal" :page-count="historyPageCount" noun="imports" />
  </Card>
</template>
