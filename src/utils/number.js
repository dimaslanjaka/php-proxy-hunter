/**
 * Format a number as Indonesian Rupiah (Rp)
 * @param {Partial<number|string|null>} value - The number to format
 * @returns {string} Formatted as Rp. 1.234,56
 */
export function toRupiah(value) {
  if (value === null || value === undefined) return 'Rp. 0';
  let num = value;
  if (typeof value === 'string') {
    num = parseFloat(value.replace(/[^\d.-]/g, ''));
  }
  if (typeof num !== 'number' || isNaN(num)) return 'Rp. 0';
  return 'Rp. ' + num.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}
