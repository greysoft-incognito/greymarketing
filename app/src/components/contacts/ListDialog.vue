<script setup lang="ts">
import type { ApiError, ContactList } from '@/api';
import { Loader2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { lists } from '@/api';
import FormField from '@/components/app/FormField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogBody, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { notifyError, toast } from '@/lib/notify';
import { useWorkspace } from '@/stores/workspace';

const props = defineProps<{ list?: ContactList | null }>();
const emit = defineEmits<{ saved: [list: ContactList] }>();

const open = defineModel<boolean>('open', { default: false });
const workspace = useWorkspace();

const name = ref('');
const description = ref('');
const busy = ref(false);
const error = ref<ApiError | null>(null);

watch(open, (value) => {
  if (value) {
    error.value = null;
    name.value = props.list?.name ?? '';
    description.value = props.list?.description ?? '';
  }
});

async function save() {
  busy.value = true;
  error.value = null;

  const data = { name: name.value, description: description.value || null };

  try {
    const result = props.list
      ? await lists.update(workspace.current!.id, props.list.id, data)
      : await lists.create(workspace.current!.id, data);

    toast.success(props.list ? 'List renamed.' : `${result.data.name} created.`);
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
    <DialogContent class="max-w-md">
      <form class="contents" @submit.prevent="save">
        <DialogHeader>
          <DialogTitle class="text-base font-semibold">
            {{ list ? 'Edit list' : 'New list' }}
          </DialogTitle>
          <DialogDescription class="text-sm text-muted-foreground">
            Lists group contacts you send to together. A contact can be on many lists.
          </DialogDescription>
        </DialogHeader>
        <DialogBody>
          <FormField id="list-name" label="Name" :error="error?.field('name')">
            <Input id="list-name" v-model="name" placeholder="Lagos customers" />
          </FormField>
          <FormField id="list-description" label="Description" hint="Optional" :error="error?.field('description')">
            <Input id="list-description" v-model="description" placeholder="Bought from a Lagos store" />
          </FormField>
        </DialogBody>
        <DialogFooter>
          <Button type="button" variant="secondary" :disabled="busy" @click="open = false">
            Cancel
          </Button>
          <Button type="submit" :disabled="busy || !name.trim()">
            <Loader2 v-if="busy" class="animate-spin" />
            {{ list ? 'Save list' : 'Create list' }}
          </Button>
        </DialogFooter>
      </form>
    </DialogContent>
  </Dialog>
</template>
