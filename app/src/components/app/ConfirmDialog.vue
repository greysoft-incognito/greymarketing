<script setup lang="ts">
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogBody, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';

const props = withDefaults(defineProps<{
  title: string;
  description: string;
  action?: string;
  run: () => Promise<unknown>;
}>(), {
  action: 'Delete',
});

const open = defineModel<boolean>('open', { default: false });
const busy = ref(false);

async function confirm() {
  busy.value = true;

  try {
    await props.run();
    open.value = false;
  } finally {
    busy.value = false;
  }
}
</script>

<template>
  <Dialog v-model:open="open">
    <DialogContent class="max-w-md">
      <DialogHeader>
        <DialogTitle class="text-base font-semibold">
          {{ title }}
        </DialogTitle>
      </DialogHeader>
      <DialogBody class="pt-2">
        <DialogDescription class="text-sm text-muted-foreground">
          {{ description }}
        </DialogDescription>
      </DialogBody>
      <DialogFooter>
        <Button variant="secondary" :disabled="busy" @click="open = false">
          Cancel
        </Button>
        <Button variant="destructive" :disabled="busy" @click="confirm">
          {{ action }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
