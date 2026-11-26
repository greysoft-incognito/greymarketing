<script setup lang="ts">
import type { CheckboxRootEmits, CheckboxRootProps } from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { reactiveOmit } from '@vueuse/core';
import { Check, Minus } from 'lucide-vue-next';
import { CheckboxIndicator, CheckboxRoot, useForwardPropsEmits } from 'reka-ui';
import { cn } from '@/lib/utils';

const props = defineProps<CheckboxRootProps & { class?: HTMLAttributes['class'] }>();
const emits = defineEmits<CheckboxRootEmits>();

const delegatedProps = reactiveOmit(props, 'class');
const forwarded = useForwardPropsEmits(delegatedProps, emits);
</script>

<template>
  <CheckboxRoot
    v-slot="{ state }"
    data-slot="checkbox"
    v-bind="forwarded"
    :class="cn(
      'grid size-4 shrink-0 cursor-pointer place-items-center rounded-[4px] bg-input outline-none transition-colors focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground data-[state=indeterminate]:bg-primary data-[state=indeterminate]:text-primary-foreground',
      props.class,
    )"
  >
    <CheckboxIndicator class="grid place-items-center">
      <Minus v-if="state === 'indeterminate'" class="size-3" />
      <Check v-else class="size-3" />
    </CheckboxIndicator>
  </CheckboxRoot>
</template>
