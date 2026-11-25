import { defineBoot } from '#q-app/wrappers';
import { useTheme } from '@/composables/useTheme';

export default defineBoot(() => {
  useTheme();
});
