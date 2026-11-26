import { useRequest } from 'alova/client';
import { providers } from '@/api';

/**
 * Looks up a provider's display name from the catalog, falling back to its key.
 */
export function useProviderNames() {
  const { data: catalog } = useRequest(providers.catalog);

  return (key: string) => catalog.value?.data.find(provider => provider.key === key)?.name ?? key;
}
