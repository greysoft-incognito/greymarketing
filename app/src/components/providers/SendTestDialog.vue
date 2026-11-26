<script setup lang="ts">
import type { ApiError, ProviderConnection } from '@/api';
import { Loader2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { connections } from '@/api';
import FormField from '@/components/app/FormField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogBody, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { notifyError, toast } from '@/lib/notify';
import { useSession } from '@/stores/session';
import { useWorkspace } from '@/stores/workspace';

const props = defineProps<{ connection: ProviderConnection | null }>();

const open = defineModel<boolean>('open', { default: false });
const workspace = useWorkspace();
const session = useSession();

const to = ref('');
const subject = ref('');
const body = ref('');
const busy = ref(false);
const error = ref<ApiError | null>(null);

watch(open, (value) => {
  if (!value || !props.connection) {
    return;
  }

  const email = props.connection.channel === 'email';

  error.value = null;
  to.value = (email ? session.user?.email : session.user?.phone) ?? '';
  subject.value = 'Test message from GreyMarketing';
  body.value = `This is a test from ${workspace.current?.name} through ${props.connection.name}. If you got it, the connection works.`;
});

async function send() {
  const connection = props.connection!;

  busy.value = true;
  error.value = null;

  try {
    await connections.sendTest(workspace.current!.id, connection.id, {
      to: to.value,
      body: body.value,
      ...(connection.channel === 'email' ? { subject: subject.value } : {}),
    });

    toast.success(`Test message sent to ${to.value}.`);
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
    <DialogContent v-if="connection" class="max-w-md">
      <DialogHeader>
        <DialogTitle class="text-base font-semibold">
          Send a test message
        </DialogTitle>
        <DialogDescription class="text-sm text-muted-foreground">
          One message through {{ connection.name }}. Your provider bills it as usual.
        </DialogDescription>
      </DialogHeader>
      <DialogBody>
        <FormField
          id="test-to"
          :label="connection.channel === 'email' ? 'Send to email' : 'Send to phone'"
          :hint="connection.channel === 'sms' ? 'International format, like +2348012345678' : undefined"
          :error="error?.field('to')"
        >
          <Input id="test-to" v-model="to" :type="connection.channel === 'email' ? 'email' : 'tel'" />
        </FormField>
        <FormField v-if="connection.channel === 'email'" id="test-subject" label="Subject" :error="error?.field('subject')">
          <Input id="test-subject" v-model="subject" />
        </FormField>
        <FormField id="test-body" label="Message" :error="error?.field('body')">
          <Textarea id="test-body" v-model="body" rows="4" />
        </FormField>
      </DialogBody>
      <DialogFooter>
        <Button variant="secondary" :disabled="busy" @click="open = false">
          Cancel
        </Button>
        <Button :disabled="busy || !to || !body" @click="send">
          <Loader2 v-if="busy" class="animate-spin" />
          Send test
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
