<script setup lang="ts">
import type { ApiError, Invitation } from '@/api';
import { Loader2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { team } from '@/api';
import FormField from '@/components/app/FormField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogBody, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { notifyError, toast } from '@/lib/notify';
import { useWorkspace } from '@/stores/workspace';

const props = defineProps<{ seatsLeft: number | null }>();
const emit = defineEmits<{ sent: [invitation: Invitation] }>();

const open = defineModel<boolean>('open', { default: false });
const workspace = useWorkspace();

const email = ref('');
const role = ref<Invitation['role']>('member');
const busy = ref(false);
const error = ref<ApiError | null>(null);

const seatsNote = computed(() => {
  if (props.seatsLeft === null) {
    return '';
  }

  return ` ${props.seatsLeft} ${props.seatsLeft === 1 ? 'seat' : 'seats'} left on your plan.`;
});

watch(open, (value) => {
  if (value) {
    email.value = '';
    role.value = 'member';
    error.value = null;
  }
});

async function send() {
  busy.value = true;
  error.value = null;

  try {
    const result = await team.invite(workspace.current!.id, { email: email.value.trim(), role: role.value });

    toast.success(result.message);
    emit('sent', result.data);
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
      <form class="contents" @submit.prevent="send">
        <DialogHeader>
          <DialogTitle class="text-base font-semibold">
            Invite a member
          </DialogTitle>
          <DialogDescription class="text-sm text-muted-foreground">
            We email them a link to join {{ workspace.current?.name }}.{{ seatsNote }}
          </DialogDescription>
        </DialogHeader>
        <DialogBody>
          <FormField id="invite-email" label="Email" :error="error?.field('email')">
            <Input id="invite-email" v-model="email" type="email" placeholder="name@company.com" autocomplete="off" required />
          </FormField>
          <FormField id="invite-role" label="Role" hint="Admins can also manage providers, billing, settings and the team." :error="error?.field('role')">
            <NativeSelect id="invite-role" v-model="role">
              <option value="member">
                Member
              </option>
              <option value="admin">
                Admin
              </option>
            </NativeSelect>
          </FormField>
        </DialogBody>
        <DialogFooter>
          <Button type="button" variant="secondary" :disabled="busy" @click="open = false">
            Cancel
          </Button>
          <Button type="submit" :disabled="busy || !email.trim()">
            <Loader2 v-if="busy" class="animate-spin" />
            Send invite
          </Button>
        </DialogFooter>
      </form>
    </DialogContent>
  </Dialog>
</template>
