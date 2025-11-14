/**
 * Checks whether a given value is a non-null object (excluding arrays).
 *
 * @param value - The value to check.
 * @returns True if the value is an object and not null or an array, otherwise false.
 */
export function isObject(value: any): value is Record<string, any> {
  return value !== null && typeof value === 'object' && !Array.isArray(value) && typeof value !== 'undefined';
}

/**
 * Checks whether a given value is an array.
 *
 * @param value - The value to check.
 * @returns True if the value is an array, otherwise false.
 */
export function isArray(value: any): value is any[] {
  return Array.isArray(value);
}

/**
 * Checks whether a given value is a string.
 *
 * @param value - The value to check.
 * @returns True if the value is a string, otherwise false.
 */
export function isString(value: any): value is string {
  return typeof value === 'string';
}

/**
 * Checks whether a given value is a valid number (not NaN).
 *
 * @param value - The value to check.
 * @returns True if the value is a number and not NaN, otherwise false.
 */
export function isNumber(value: any): value is number {
  return typeof value === 'number' && !isNaN(value);
}

/**
 * Checks whether a given value is a boolean.
 *
 * @param value - The value to check.
 * @returns True if the value is a boolean, otherwise false.
 */
export function isBoolean(value: any): value is boolean {
  return typeof value === 'boolean';
}

/**
 * Checks whether a given value is a function.
 *
 * @param value - The value to check.
 * @returns True if the value is a function, otherwise false.
 */
export function isFunction(value: any): value is (...args: any[]) => any {
  return typeof value === 'function';
}
