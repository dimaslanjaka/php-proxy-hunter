/**
 * Convert a string to HTML entities.
 * @param {string} str - The input string.
 * @returns {string} The string with HTML entities where applicable.
 */
export function toHtmlEntities(str) {
  const entitiesMap = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;'
  };

  return str.replace(/[&<>"']/g, (char) => entitiesMap[char]);
}

/**
 * Checks if the provided value is a string.
 *
 * @param {any} str - The value to check.
 * @returns {boolean} `true` if the value is a string; otherwise, `false`.
 */
export function isString(str) {
  return typeof str === 'string' || str instanceof String;
}

/**
 * Checks whether a value is not empty.
 *
 * This is a convenience wrapper around `isEmpty` that returns the negation.
 *
 * @param {any} data - The value to test for non-emptiness.
 * @returns {boolean} `true` if the value is not empty; otherwise, `false`.
 *
 * @example
 * isNotEmpty('hello'); // true
 * isNotEmpty('   ');   // false
 * isNotEmpty([1]);     // true
 * isNotEmpty({});      // false
 */
export function isNotEmpty(str) {
  return !isEmpty(str);
}

/**
 * Determines whether a value is considered "empty".
 *
 * The following are treated as empty:
 * - null or undefined
 * - strings that are empty or contain only whitespace (uses trim())
 * - arrays with length === 0
 * - plain objects with no own enumerable keys (Object.keys(obj).length === 0)
 *
 * Note: other object types (e.g., Map, Set, Date, Buffer) are treated as objects;
 * their emptiness depends on Object.keys() which may not reflect their internal size.
 *
 * @param {any} data - The value to check for emptiness.
 * @returns {boolean} `true` if the value is empty according to the rules above; otherwise, `false`.
 *
 * @example
 * isEmpty(null);        // true
 * isEmpty(undefined);   // true
 * isEmpty('');          // true
 * isEmpty('  ');        // true
 * isEmpty([]);          // true
 * isEmpty({});          // true
 * isEmpty([1,2]);       // false
 * isEmpty({ a: 1 });    // false
 */
export function isEmpty(data) {
  return (
    data === null ||
    data === undefined ||
    (isString(data) && data.trim().length === 0) ||
    (Array.isArray(data) && data.length === 0) ||
    (typeof data === 'object' && Object.keys(data).length === 0)
  );
}

/**
 * Extracts the first JSON array or object from a string.
 *
 * @param {string} str - The input string.
 * @param {boolean} [debug=false] - If true, logs match and parse information.
 * @returns {{ parsed: any, raw: string } | null}
 */
export function extractJson(str, debug = false) {
  const jsonRegex = /(\[.*?\]|\{.*?\})/s;
  const match = str.match(jsonRegex);

  if (debug) console.log('🔍 Match result:', match);

  if (match) {
    const raw = match[1];
    try {
      const parsed = JSON.parse(raw);
      if (debug) console.log('✅ Parsed JSON:', parsed);
      return { parsed, raw };
    } catch (e) {
      if (debug) console.error('❌ JSON.parse error:', e.message);
    }
  } else {
    if (debug) console.warn('⚠️ No JSON-like structure found.');
  }

  return null;
}
