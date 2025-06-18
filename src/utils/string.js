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
 * Checks if the provided string is non-empty.
 *
 * @param {any} str - The value to check.
 * @returns {boolean} `true` if the value is a non-empty string; otherwise, `false`.
 */
export function isNotEmpty(str) {
  return isString(str) && str.length > 0;
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
