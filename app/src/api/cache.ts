import { invalidateCache } from 'alova';
import { alova } from './client';

/**
 * Drop cached responses for requests whose name matches, so the next read goes to the API.
 */
export async function forget(...names: Array<string | RegExp>): Promise<void> {
  await invalidateCache(names.flatMap(name => alova.snapshots.match(name)));
}

/**
 * Drop every cached response, for example when the user signs out.
 */
export async function forgetAll(): Promise<void> {
  await invalidateCache();
}
