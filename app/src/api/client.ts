import { createAlova } from 'alova';
import adapterFetch from 'alova/fetch';
import VueHook from 'alova/vue';
import { useSession } from '@/stores/session';

/**
 * An API response that was not successful, with Laravel's validation errors when present.
 */
export class ApiError extends Error {
  constructor(
    message: string,
    readonly status: number,
    readonly errors: Record<string, string | string[]> = {},
  ) {
    super(message);
    this.name = 'ApiError';
  }

  /**
   * The first message for a field, if the API rejected it.
   */
  field(name: string): string | undefined {
    const error = this.errors[name];

    return Array.isArray(error) ? error[0] : error;
  }
}

export const alova = createAlova({
  baseURL: `${process.env.API_URL}/api`,
  statesHook: VueHook,
  requestAdapter: adapterFetch(),
  cacheFor: { GET: 2 * 60 * 1000 },
  timeout: 30000,

  beforeRequest(method) {
    const session = useSession();

    method.config.headers = {
      Accept: 'application/json',
      ...(method.data instanceof FormData ? {} : { 'Content-Type': 'application/json' }),
      ...(session.token ? { Authorization: `Bearer ${session.token}` } : {}),
      ...method.config.headers,
    };
  },

  responded: {
    async onSuccess(response) {
      if (response.status === 204) {
        return null;
      }

      const body = await response.json().catch(() => ({}));

      if (response.status === 401) {
        useSession().expire();
      }

      if (!response.ok) {
        throw new ApiError(body.message ?? response.statusText, response.status, body.errors ?? {});
      }

      return body;
    },
  },
});
