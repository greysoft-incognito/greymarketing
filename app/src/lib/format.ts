const numberFormat = new Intl.NumberFormat('en-NG');

export function formatNumber(value: number): string {
  return numberFormat.format(value);
}

export function formatMoney(amount: number, currency: string): string {
  return new Intl.NumberFormat('en-NG', { style: 'currency', currency, maximumFractionDigits: amount % 1 ? 2 : 0 }).format(amount);
}

export function formatDateTime(value: string | null): string {
  if (!value) {
    return '';
  }

  return new Intl.DateTimeFormat('en-NG', { day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' }).format(new Date(value));
}

export function formatDay(value: string): string {
  return new Intl.DateTimeFormat('en-NG', { day: 'numeric', month: 'short' }).format(new Date(value));
}

/**
 * Percentage change from one value to another, or null when there is nothing to compare with.
 */
export function percentChange(current: number, previous: number): number | null {
  if (!previous) {
    return null;
  }

  return Math.round((current - previous) / previous * 1000) / 10;
}

const relativeFormat = new Intl.RelativeTimeFormat('en', { numeric: 'auto' });

/**
 * A short "5 minutes ago" style label, falling back to the date after a week.
 */
export function formatRelative(value: string | null): string {
  if (!value) {
    return '';
  }

  const seconds = Math.round((new Date(value).getTime() - Date.now()) / 1000);
  const units: Array<[Intl.RelativeTimeFormatUnit, number]> = [['second', 60], ['minute', 60], ['hour', 24], ['day', 7]];
  let amount = seconds;

  for (const [unit, size] of units) {
    if (Math.abs(amount) < size) {
      return unit === 'second' ? 'just now' : relativeFormat.format(amount, unit);
    }

    amount = Math.round(amount / size);
  }

  return formatDay(value);
}

/**
 * A person's display name from whatever parts they have.
 */
export function contactName(contact: { first_name: string | null; last_name: string | null; email: string | null; phone: string | null }): string {
  return [contact.first_name, contact.last_name].filter(Boolean).join(' ') || contact.email || contact.phone || 'Unnamed';
}
