<script setup lang="ts">
import type { ApiError, Member } from '@/api';
import { Loader2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { team } from '@/api';
import FormField from '@/components/app/FormField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogBody, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { notifyError, toast } from '@/lib/notify';
import { useWorkspace } from '@/stores/workspace';

const props = defineProps<{ member: Member | null }>();
const emit = defineEmits<{ transferred: [] }>();

const open = defineModel<boolean>('open', { default: false });
const workspace = useWorkspace();

const password = ref('');
const busy = ref(false);
const error = ref<ApiError | null>(null);

const firstName = computed(() => props.member?.name.split(' ')[0] ?? '');

watch(open, (value) => {
  if (value) {
    password.value = '';
    error.value = null;
  }
});

async function transfer() {
  busy.value = true;
  error.value = null;

  try {
    const result = await team.transferOwnership(workspace.current!.id, props.member!.id, password.value);

    toast.success(result.message);
    open.value = false;
    emit('transferred');
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
      <form class="contents" @submit.prevent="transfer">
        <DialogHeader>
          <DialogTitle class="text-base font-semibold">
            Make {{ member?.name }} the owner?
          </DialogTitle>
          <DialogDescription class="text-sm text-muted-foreground">
            {{ firstName }} gets full control of {{ workspace.current?.name }}, including billing and deleting the workspace.
            You stay on as an admin. Only {{ firstName }} can give ownership back.
          </DialogDescription>
        </DialogHeader>
        <DialogBody>
          <FormField id="transfer-password" label="Your password" hint="Your plan, auto-renewal and saved card stay as they are." :error="error?.field('password')">
            <Input id="transfer-password" v-model="password" type="password" placeholder="Enter your password to confirm" autocomplete="current-password" required />
          </FormField>
        </DialogBody>
        <DialogFooter>
          <Button type="button" variant="secondary" :disabled="busy" @click="open = false">
            Cancel
          </Button>
          <Button type="submit" variant="destructive" :disabled="busy || !password">
            <Loader2 v-if="busy" class="animate-spin" />
            Transfer ownership
          </Button>
        </DialogFooter>
      </form>
    </DialogContent>
  </Dialog>
</template>
