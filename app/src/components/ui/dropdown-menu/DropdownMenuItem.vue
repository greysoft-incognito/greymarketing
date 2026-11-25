<script setup lang="ts">
import type { DropdownMenuItemProps } from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { reactiveOmit } from '@vueuse/core';
import { DropdownMenuItem, useForwardProps } from 'reka-ui';
import { cn } from '@/lib/utils';

const props = withDefaults(defineProps<DropdownMenuItemProps & {
  class?: HTMLAttributes['class'];
  variant?: 'default' | 'destructive';
}>(), {
  variant: 'default',
});

const delegatedProps = reactiveOmit(props, 'class', 'variant');
const forwarded = useForwardProps(delegatedProps);
</script>

<template>
  <DropdownMenuItem
    data-slot="dropdown-menu-item"
    :data-variant="variant"
    v-bind="forwarded"
    :class="cn(
      'relative flex cursor-default items-center gap-2 rounded-md px-2 py-1.5 text-sm outline-none select-none focus:bg-muted data-[disabled]:pointer-events-none data-[disabled]:opacity-50 data-[variant=destructive]:text-destructive [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*=\'size-\'])]:size-4 [&_svg:not([class*=\'text-\'])]:text-muted-foreground',
      props.class,
    )"
  >
    <slot />
  </DropdownMenuItem>
</template>
