/**
 * Build a CSV string, quoting cells that need it.
 */
export function toCsv(rows: Array<Array<string | number | null | undefined>>): string {
  return rows
    .map(row => row.map((cell) => {
      const value = cell == null ? '' : String(cell);

      return /[",\n\r]/.test(value) ? `"${value.replace(/"/g, '""')}"` : value;
    }).join(','))
    .join('\n');
}

/**
 * Hand the browser a CSV file to save.
 */
export function downloadCsv(name: string, rows: Array<Array<string | number | null | undefined>>): void {
  const url = URL.createObjectURL(new Blob([toCsv(rows)], { type: 'text/csv;charset=utf-8' }));
  const link = document.createElement('a');

  link.href = url;
  link.download = name;
  link.click();

  URL.revokeObjectURL(url);
}
