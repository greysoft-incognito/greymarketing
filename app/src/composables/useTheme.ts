import { useColorMode } from '@vueuse/core';
import { computed } from 'vue';

/**
 * Light, dark or the system setting, applied as a `dark` class on <html>.
 */
export function useTheme() {
  const mode = useColorMode({ storageKey: 'gm.theme', emitAuto: true });

  const isDark = computed(() => mode.state.value === 'dark');

  function toggle() {
    mode.value = isDark.value ? 'light' : 'dark';
  }

  return { mode, isDark, toggle };
}
