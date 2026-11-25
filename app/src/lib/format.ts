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
