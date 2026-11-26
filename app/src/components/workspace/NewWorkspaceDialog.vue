<script setup lang="ts">
import type { ApiError } from '@/api';
import { Loader2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import FormField from '@/components/app/FormField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogBody, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { notifyError, toast } from '@/lib/notify';
import { useWorkspace } from '@/stores/workspace';

const open = defineModel<boolean>('open', { default: false });

const router = useRouter();
const workspace = useWorkspace();

const name = ref('');
const busy = ref(false);
const error = ref<ApiError | null>(null);

watch(open, (value) => {
  if (value) {
    name.value = '';
    error.value = null;
  }
});

async function create() {
  busy.value = true;
  error.value = null;

  try {
    const created = await workspace.create(name.value.trim());

    toast.success(`${created.name} created. You're now working in it.`);
    open.value = false;
    await router.push({ name: 'dashboard' });
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
      <form class="contents" @submit.prevent="create">
        <DialogHeader>
          <DialogTitle class="text-base font-semibold">
            New workspace
          </DialogTitle>
          <DialogDescription class="text-sm text-muted-foreground">
            You become its owner. Contacts, providers and templates are not shared between workspaces.
          </DialogDescription>
        </DialogHeader>
        <DialogBody>
          <FormField id="workspace-new-name" label="Workspace name" :error="error?.field('name')">
            <Input id="workspace-new-name" v-model="name" placeholder="Kola Catering" maxlength="100" />
          </FormField>
        </DialogBody>
        <DialogFooter>
          <Button type="button" variant="secondary" :disabled="busy" @click="open = false">
            Cancel
          </Button>
          <Button type="submit" :disabled="busy || !name.trim()">
            <Loader2 v-if="busy" class="animate-spin" />
            Create workspace
          </Button>
        </DialogFooter>
      </form>
    </DialogContent>
  </Dialog>
</template>
