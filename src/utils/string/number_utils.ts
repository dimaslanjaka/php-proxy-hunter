/**
 * Convert a value to a number in a safe way.
 *
 * Handles:
 * - number: returned as-is
 * - string: parsed with parseFloat after trimming whitespace
 * - null | undefined: treated as 0
 * - empty or non-numeric strings: treated as 0
 *
 * Examples:
 * toNumber(10)        // 10
 * toNumber(" 3.14 ")  // 3.14
 * toNumber("")        // 0
 * toNumber(null)      // 0
 *
 * @param value - The value to convert to number. May be string, number, null, or undefined.
 * @returns The numeric value parsed from the input, or 0 if conversion fails or input is null/undefined.
 */
export function toNumber(value: string | number | null | undefined): number {
  if (value === null || value === undefined) {
    return 0;
  }

  if (typeof value === 'number') {
    return value;
  }

  const trimmed = value.trim();
  if (trimmed === '') {
    return 0;
  }

  const parsed = parseFloat(trimmed);
  return Number.isNaN(parsed) ? 0 : parsed;
}
