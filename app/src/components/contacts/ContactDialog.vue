<script setup lang="ts">
import type { ApiError, Contact, ContactList } from '@/api';
import { Loader2, Plus, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { contacts } from '@/api';
import FormField from '@/components/app/FormField.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogBody, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
import { Switch } from '@/components/ui/switch';
import { notifyError, toast } from '@/lib/notify';
import { useWorkspace } from '@/stores/workspace';

const props = defineProps<{
  contact?: Contact | null;
  lists: ContactList[];
  defaultList?: string | null;
}>();

const emit = defineEmits<{ saved: [contact: Contact] }>();

const open = defineModel<boolean>('open', { default: false });
const workspace = useWorkspace();

const form = ref({ first_name: '', last_name: '', email: '', phone: '' });
const subscribed = ref({ email: true, sms: true });
const selectedLists = ref<string[]>([]);
const fields = ref<Array<{ key: string; value: string }>>([]);
const busy = ref(false);
const error = ref<ApiError | null>(null);

const editing = computed(() => props.contact ?? null);

watch(open, (value) => {
  if (!value) {
    return;
  }

  const contact = props.contact;

  error.value = null;
  form.value = {
    first_name: contact?.first_name ?? '',
    last_name: contact?.last_name ?? '',
    email: contact?.email ?? '',
    phone: contact?.phone ?? '',
  };
  subscribed.value = { email: contact?.email_subscribed ?? true, sms: contact?.sms_subscribed ?? true };
  selectedLists.value = contact?.lists?.map(list => list.id) ?? (props.defaultList ? [props.defaultList] : []);
  fields.value = Object.entries(contact?.fields ?? {}).map(([key, value]) => ({ key, value: value ?? '' }));
});

function toggleList(id: string, checked: boolean | 'indeterminate') {
  selectedLists.value = checked === true
    ? [...selectedLists.value, id]
    : selectedLists.value.filter(item => item !== id);
}

/**
 * Field names become merge tags, so keep them simple.
 */
function fieldKey(value: string) {
  return value.trim().toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
}

async function save() {
  busy.value = true;
  error.value = null;

  const data = {
    first_name: form.value.first_name || null,
    last_name: form.value.last_name || null,
    email: form.value.email || null,
    phone: form.value.phone || null,
    email_subscribed: subscribed.value.email,
    sms_subscribed: subscribed.value.sms,
    lists: selectedLists.value,
    fields: Object.fromEntries(fields.value.filter(field => fieldKey(field.key)).map(field => [fieldKey(field.key), field.value])),
  };

  try {
    const result = editing.value
      ? await contacts.update(workspace.current!.id, editing.value.id, data)
      : await contacts.create(workspace.current!.id, data);

    toast.success(editing.value ? 'Contact updated.' : 'Contact added.');
    emit('saved', result.data);
    open.value = false;
  } catch (e) {
    error.value = notifyError(e);
  } finally {
    busy.value = false;
  }
}
</script>

<template>
  <Dialog v-model:open="open">
    <DialogContent class="max-w-lg">
      <DialogHeader>
        <DialogTitle class="text-base font-semibold">
          {{ editing ? 'Edit contact' : 'Add a contact' }}
        </DialogTitle>
        <DialogDescription class="text-sm text-muted-foreground">
          An email address or a phone number is enough. Numbers without a country code use your workspace country.
        </DialogDescription>
      </DialogHeader>

      <DialogBody>
        <div class="grid gap-4 sm:grid-cols-2">
          <FormField id="contact-first" label="First name" :error="error?.field('first_name')">
            <Input id="contact-first" v-model="form.first_name" autocomplete="off" />
          </FormField>
          <FormField id="contact-last" label="Last name" :error="error?.field('last_name')">
            <Input id="contact-last" v-model="form.last_name" autocomplete="off" />
          </FormField>
          <FormField id="contact-email" label="Email" :error="error?.field('email')">
            <Input id="contact-email" v-model="form.email" type="email" autocomplete="off" />
          </FormField>
          <FormField id="contact-phone" label="Phone" :error="error?.field('phone')">
            <Input id="contact-phone" v-model="form.phone" type="tel" autocomplete="off" placeholder="0803 412 0098" />
          </FormField>
        </div>

        <div class="grid gap-2.5">
          <label class="flex cursor-pointer items-center gap-2.5 text-sm">
            <Switch v-model="subscribed.email" />
            Subscribed to email
          </label>
          <label class="flex cursor-pointer items-center gap-2.5 text-sm">
            <Switch v-model="subscribed.sms" />
            Subscribed to SMS
          </label>
        </div>

        <template v-if="lists.length">
          <Separator />
          <div class="grid gap-2">
            <span class="text-sm font-medium">Lists</span>
            <div class="grid gap-2 sm:grid-cols-2">
              <label v-for="list in lists" :key="list.id" class="flex cursor-pointer items-center gap-2.5 text-sm">
                <Checkbox :model-value="selectedLists.includes(list.id)" @update:model-value="toggleList(list.id, $event)" />
                <span class="truncate">{{ list.name }}</span>
              </label>
            </div>
          </div>
        </template>

        <Separator />

        <div class="grid gap-2">
          <div class="flex items-center justify-between">
            <span class="text-sm font-medium">Custom fields</span>
            <Button variant="ghost" size="sm" @click="fields.push({ key: '', value: '' })">
              <Plus />
              Add field
            </Button>
          </div>
          <p v-if="!fields.length" class="text-xs text-muted-foreground">
            Extra details like city or last order. Use them in messages as merge tags.
          </p>
          <div v-for="(field, index) in fields" :key="index" class="grid grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)_auto] gap-2">
            <Input v-model="field.key" placeholder="city" aria-label="Field name" />
            <Input v-model="field.value" placeholder="Lagos" aria-label="Field value" />
            <Button variant="ghost" size="icon" aria-label="Remove field" @click="fields.splice(index, 1)">
              <X />
            </Button>
          </div>
        </div>
      </DialogBody>

      <DialogFooter>
        <Button variant="secondary" :disabled="busy" @click="open = false">
          Cancel
        </Button>
        <Button :disabled="busy || (!form.email && !form.phone)" @click="save">
          <Loader2 v-if="busy" class="animate-spin" />
          {{ editing ? 'Save contact' : 'Add contact' }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
