/**
 * Check whether two strings are equal, ignoring letter case.
 *
 * Compares `str` and `pattern` after converting both to lower case using
 * `String.prototype.toLowerCase()` and returns `true` when they match exactly.
 *
 * This is a simple, fast case-insensitive equality check. It is not intended
 * for locale-sensitive or full Unicode case folding — for those cases use
 * Intl.Collator or a proper Unicode-aware library.
 *
 * @param str - The first string to compare.
 * @param pattern - The second string (pattern) to compare against.
 * @returns `true` if the two strings are identical when case is ignored; otherwise `false`.
 *
 * @example
 * isMatchIgnoreCase('Hello', 'hello'); // true
 *
 * @since 1.0.0
 */
export function isMatchIgnoreCase(str: string, pattern: string): boolean {
  return str.toLowerCase() === pattern.toLowerCase();
}
