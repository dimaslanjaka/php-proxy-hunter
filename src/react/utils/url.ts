import { viteBaseUrl } from './index';

/**
 * Validates whether a string is a valid HTTP or HTTPS URL.
 *
 * @param str The string to validate as a URL.
 * @returns True if the string is a valid HTTP/HTTPS URL, false otherwise.
 */
export function isValidHttpUrl(str: string): boolean {
  let url;
  try {
    url = new URL(str);
  } catch (_) {
    return false;
  }
  return url.protocol === 'http:' || url.protocol === 'https:';
}

/**
 * Creates a full URL string from a given path and optional query parameters.
 *
 * - If the path ends with a slash, 'index.html' is appended.
 * - If the path contains '.php', the origin is set to 'localhost'.
 * - Otherwise, uses the current window location origin.
 *
 * @param path The path to append to the origin (can be relative or absolute).
 * @param params An object containing query parameters to append to the URL.
 * @returns The constructed URL as a string.
 */
export function createUrl(path: string, params: Record<string, any> = {}): string {
  console.debug(`[createUrl] path: "${path}", params:`, params);
  let origin = window.location.origin; // Default to current origin
  let base = '';
  if (viteBaseUrl && viteBaseUrl !== '/') {
    base = viteBaseUrl.endsWith('/') ? viteBaseUrl.slice(0, -1) : viteBaseUrl;
  }
  if (path.endsWith('/')) {
    path += 'index.html'; // Ensure path ends with a file if it is a directory
  }
  if (path.includes('.php')) {
    // If it's a PHP file, use vite backend origin
    origin = 'https://' + (import.meta.env.VITE_BACKEND_HOSTNAME || 'dev.webmanajemen.com'); // Laragon, XAMPP, etc.
    base = '';
  }
  // Prepend vite base to path if not PHP and viteBaseUrl is set
  if (!path.includes('.php') && base) {
    // Avoid double slashes
    path = base + (path.startsWith('/') ? path : '/' + path);
  }

  const url = new URL(path, origin);
  Object.keys(params).forEach((key) => {
    url.searchParams.append(key, String(params[key]));
  });
  const result = url.toString();

  console.debug(`[createUrl] result: "${result}"`);

  return result;
}
