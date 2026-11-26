import type { SmsInfo } from '@/api';

/**
 * Same `{{ key }}` and `{{ key | fallback }}` syntax the API renders.
 */
const TAG = /\{\{\s*([\w.]+)\s*(?:\|\s*([^}]*?))?\s*\}\}/g;

const GSM_BASIC = '@£$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !"#¤%&\'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà';
const GSM_EXTENDED = '^{}\\[~]|€\f';

/**
 * Tags every contact has. Custom fields from imports can be used too.
 */
export const STANDARD_TAGS = ['first_name', 'last_name', 'full_name', 'email', 'phone', 'unsubscribe_url'];

export function renderTags(content: string | null | undefined, data: Record<string, unknown>): string {
  return (content ?? '').replace(TAG, (_, key: string, fallback?: string) => {
    const value = key.split('.').reduce<unknown>((carry, part) => (carry as Record<string, unknown> | undefined)?.[part], data);

    return value === null || value === undefined || value === '' ? (fallback ?? '') : String(value);
  });
}

export function tagsIn(content: string | null | undefined): string[] {
  return [...new Set([...(content ?? '').matchAll(TAG)].map(match => match[1]!))];
}

/**
 * How many SMS parts a message needs, which is what providers bill by.
 */
export function smsSegments(text: string): SmsInfo {
  let length = 0;

  for (const char of text) {
    if (GSM_BASIC.includes(char)) {
      length++;
    } else if (GSM_EXTENDED.includes(char)) {
      length += 2;
    } else {
      const units = text.length;

      return { encoding: 'ucs2', length: units, segments: units <= 70 ? 1 : Math.ceil(units / 67) };
    }
  }

  return { encoding: 'gsm7', length, segments: length <= 160 ? 1 : Math.ceil(length / 153) };
}

/**
 * Characters left before the message needs another part.
 */
export function smsRemaining(info: SmsInfo): number {
  const single = info.encoding === 'gsm7' ? 160 : 70;
  const part = info.encoding === 'gsm7' ? 153 : 67;

  return (info.segments === 1 ? single : info.segments * part) - info.length;
}
