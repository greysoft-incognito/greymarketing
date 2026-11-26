import type { Branding } from '@/api';
import { useRequest } from 'alova/client';
import { computed } from 'vue';
import { branding } from '@/api';
import defaultImage from '@/assets/auth-panel.svg';
import { authCopy } from '@/lib/authCopy';

/**
 * The sign-in panel's image and text, falling back to the app's defaults.
 */
export function useBranding() {
  const { data } = useRequest(() => branding.show());

  const image = computed(() => data.value?.data.auth_image || defaultImage);

  function message(page: keyof Branding['auth_messages']) {
    return computed(() => data.value?.data.auth_messages?.[page] || authCopy[page]);
  }

  return { image, message };
}
