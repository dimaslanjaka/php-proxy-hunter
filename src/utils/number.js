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

/**
 * Format a number with comma thousand separators using the en-US locale.
 *
 * - Returns the numeric value formatted with commas for thousands separators.
 * - Returns the string "0" for null/undefined or when the value cannot be parsed as a number.
 *
 * Examples:
 *   formatNumberWithThousandSeparators(1234567)    // "1,234,567"
 *   formatNumberWithThousandSeparators('1234.5')  // "1,234.5"
 *   formatNumberWithThousandSeparators(null)      // "0"
 *
 * @param {number|string|null|undefined} value - Number or numeric string to format.
 * @returns {string} Formatted number with commas, or "0" for invalid/empty input.
 */
export function formatNumberWithThousandSeparators(value) {
  if (value === null || value === undefined) return '0';
  let num = value;
  if (typeof value === 'string') {
    num = parseFloat(value.replace(/[^\d.-]/g, ''));
  }
  if (typeof num !== 'number' || isNaN(num)) return '0';
  return num.toLocaleString('en-US');
}

/**
 * Normalize and clean a phone number.
 *
 * - Returns an empty string for null or undefined.
 * - Returns the string "0" when the input is the numeric value 0.
 * - For other falsy values returns an empty string.
 * - Converts numeric inputs to strings and strips all non-digit characters.
 *
 * Examples:
 *   fixPhoneNumber(null)    // ""
 *   fixPhoneNumber(0)       // "0"
 *   fixPhoneNumber("0812-345-678") // "0812345678"
 *   fixPhoneNumber("+62 812 3456 789") // "628123456789"
 *
 * @param {string|number|null|undefined} phone - The phone number to normalize.
 * @returns {string} The cleaned phone number containing only digits, or '' / '0' for special cases.
 */
export function fixPhoneNumber(phone) {
  if (phone === null || phone === undefined) return '';
  if (phone === 0) return '0';
  if (!phone) return '';

  // Convert to string if it's a number
  const phoneStr = typeof phone === 'number' ? phone.toString() : phone;

  // Remove non-numeric characters
  const cleaned = phoneStr.replace(/[^\d]/g, '');
  return cleaned;
}
