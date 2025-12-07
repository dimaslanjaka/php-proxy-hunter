export function formatLatency(value: any): string {
  if (value === null || value === undefined) return '-';
  const s = String(value).trim();
  if (s === '' || s === '-' || s.toLowerCase() === 'n/a') return '-';
  // replace comma decimal separator with dot, then parse
  const parsed = Number(s.replace(/,/g, '.'));
  if (Number.isNaN(parsed)) return s;
  return `${Math.round(parsed)} ms`;
}
