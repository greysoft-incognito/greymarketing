import { toast } from 'vue-sonner';
import { ApiError } from '@/api';

/**
 * Show a failed request as a toast and return the error for field messages.
 */
export function notifyError(error: unknown, fallback = 'Something went wrong. Please try again.'): ApiError | null {
  const api = error instanceof ApiError ? error : null;

  toast.error(api?.message || fallback);

  return api;
}

export { toast };
