<script setup lang="ts">
import type { DialogContentEmits, DialogContentProps } from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { reactiveOmit } from '@vueuse/core';
import { DialogContent, DialogOverlay, DialogPortal, useForwardPropsEmits } from 'reka-ui';
import { cn } from '@/lib/utils';

const props = withDefaults(defineProps<DialogContentProps & {
  class?: HTMLAttributes['class'];
  side?: 'left' | 'right';
}>(), {
  side: 'left',
});
const emits = defineEmits<DialogContentEmits>();

const delegatedProps = reactiveOmit(props, 'class', 'side');
const forwarded = useForwardPropsEmits(delegatedProps, emits);
</script>

<template>
  <DialogPortal>
    <DialogOverlay class="fixed inset-0 z-50 bg-black/40 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0" />
    <DialogContent
      data-slot="sheet-content"
      v-bind="forwarded"
      :class="cn(
        'fixed inset-y-0 z-50 flex h-full w-[270px] flex-col bg-sidebar shadow-xl transition ease-in-out data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:duration-200 data-[state=open]:duration-300',
        side === 'left' ? 'left-0 data-[state=closed]:slide-out-to-left data-[state=open]:slide-in-from-left' : 'right-0 data-[state=closed]:slide-out-to-right data-[state=open]:slide-in-from-right',
        props.class,
      )"
    >
      <slot />
    </DialogContent>
  </DialogPortal>
</template>
