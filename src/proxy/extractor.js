import ProxyData from './ProxyData.js';
import { isValidProxy } from './validator.js';

/**
 * Extracts valid HTTP/HTTPS URLs from a given string.
 * @param {string | null} string - The input string from which to extract URLs.
 * @returns {string[]} A list of unique extracted URLs. If no URLs are found or the input is null, returns an empty list.
 */
export function extractUrl(string) {
  if (!string) {
    return [];
  }

  const urlRegex = /https?:\/\/[^\s/$.?#].[^\s]*/g;
  const extractedUrls = string.match(urlRegex) || [];
  return Array.from(new Set(extractedUrls)); // Ensure unique results
}

/**
 * Extracts proxies from a string and returns an array of ProxyData instances.
 *
 * Supports multiple input formats:
 * - ip:port
 * - ip:port@username:password
 * - username:password@ip:port
 * - whitespace-separated ip and port pairs
 * - JSON snippets containing "ip" and "port" fields
 *
 * The function attempts to validate and deduplicate results. When a proxy with
 * credentials is found, the implementation prefers the entry that contains
 * credentials for the same ip:port.
 *
 * @param {string|null} string - The input string containing proxies in various formats.
 * @returns {ProxyData[]} Array of `ProxyData` objects. Each `ProxyData` will have
 *  - `proxy` (string): the `ip:port` address
 *  - `username` (string|undefined): extracted username if present
 *  - `password` (string|undefined): extracted password if present
 *
 * Entries that cannot be parsed into a valid proxy are omitted.
 */
export function extractProxies(string) {
  if (!string || !string.trim()) return [];
  // We'll build normalized entries with shape: { proxy: 'ip:port', username?: 'u', password?: 'p' }
  const entries = [];

  const ipPortPattern =
    /((?:(?:\d{1,3}\.){3}\d{1,3}):\d{2,5}(?:@\w+:\w+)?|(?:\w+:\w+@(?:\d{1,3}\.){3}\d{1,3}:\d{2,5}))/g;
  const matches1 = string.match(ipPortPattern) || [];

  const ipPortWhitespacePattern = /((?!0)\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\s+((?!0)\d{2,5})/g;
  const matches2 = Array.from(string.matchAll(ipPortWhitespacePattern));

  const ipPortJsonPattern =
    /"ip"\s*:\s*"((?!0)\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})"\s*,\s*"port"\s*:\s*"((?!0)\d{2,5})"/g;
  const matches3 = Array.from(string.matchAll(ipPortJsonPattern));

  // Extract potential user/pass from surrounding JSON
  const userMatch = string.match(/"user"\s*:\s*"([^"]+)"/);
  const passMatch = string.match(/"pass"\s*:\s*"([^"]+)"/);
  const jsonUser = userMatch ? userMatch[1] : undefined;
  const jsonPass = passMatch ? passMatch[1] : undefined;

  // process matches1 (strings)
  matches1.forEach((m) => {
    if (m.includes('@')) {
      const parts = m.split('@');
      const left = parts[0];
      const right = parts[1];
      if (isValidProxy(left)) {
        // ip:port@user:pass
        const [username, password] = right.split(':');
        entries.push({ proxy: left, username, password });
      } else if (isValidProxy(right)) {
        // user:pass@ip:port
        const [username, password] = left.split(':');
        entries.push({ proxy: right, username, password });
      } else {
        entries.push({ proxy: m });
      }
    } else {
      entries.push({ proxy: m });
    }
  });

  // process whitespace matches
  matches2.forEach((m) => {
    // m[1] = ip, m[2] = port
    if (m && m[1] && m[2]) {
      entries.push({ proxy: `${m[1]}:${m[2]}` });
    }
  });

  // process json matches and attach json user/pass if present
  matches3.forEach((m) => {
    if (m && m[1] && m[2]) {
      entries.push({ proxy: `${m[1]}:${m[2]}`, username: jsonUser, password: jsonPass });
    }
  });

  // Deduplicate, prioritizing entries that have credentials
  const map = new Map();
  entries.forEach((e) => {
    const key = e.proxy;
    if (!key) return;
    if (!isValidProxy(key)) return;
    const existing = map.get(key);
    if (!existing) {
      map.set(key, e);
    } else {
      const existingHasCreds = existing.username && existing.password;
      const newHasCreds = e.username && e.password;
      if (!existingHasCreds && newHasCreds) {
        map.set(key, e);
      }
    }
  });

  // Convert to ProxyData instances
  return Array.from(map.values()).map((e) => {
    const pd = new ProxyData();
    pd.proxy = e.proxy;
    if (e.username) pd.username = e.username;
    if (e.password) pd.password = e.password;
    return pd;
  });
}

/**
 * Converts a list of extracted proxies to an array of objects containing proxy details.
 * Each object includes `auth` (credentials if present), `username`, `password`, `address` (the IP:PORT pair),
 * `ip`, and `port`.
 *
 * @param {string} str - The input string containing proxies.
 * @returns {{username: string | undefined, password: string | undefined, auth: string | undefined, address: string, ip: string | undefined, port: string | undefined}[]}
 * An array where each entry is an object with `address` in `IP:PORT` format, and if credentials are present,
 * it includes `auth` (the full `username:password`), `username`, `password`, `ip`, and `port`. Entries with invalid proxies or invalid auth format are excluded.
 */
export function extractProxiesToObject(str) {
  // extractProxies now returns ProxyData instances
  return extractProxies(str)
    .map((pd) => {
      const address = pd.proxy || '';
      if (!address.includes('@') && !pd.username) {
        // simple ip:port
        const [ip, port] = address.split(':');
        if (!isValidProxy(address)) return null;
        return { username: undefined, password: undefined, auth: undefined, address, ip, port };
      }

      // If credentials are present on ProxyData
      if (pd.username && pd.password) {
        const auth = `${pd.username}:${pd.password}`;
        const [ip, port] = (pd.proxy || '').split(':');
        if (!isValidProxy(pd.proxy)) return null;
        return { username: pd.username, password: pd.password, auth, address, ip, port };
      }

      // If the original proxy string had credentials embedded (unlikely here), try to parse address
      if (address.includes('@')) {
        const [left, right] = address.split('@');
        const auth = left.includes(':') ? left : undefined;
        const addr = right || left;
        if (!isValidProxy(addr)) return null;
        const [ip, port] = addr.split(':');
        if (auth && auth.includes(':')) {
          const [username, password] = auth.split(':');
          return { username, password, auth, address: addr, ip, port };
        }
        return { username: undefined, password: undefined, auth: undefined, address: addr, ip, port };
      }

      // Fallback: no usable data
      return null;
    })
    .filter((o) => o !== null);
}

/**
 * Extracts all unique IP addresses from a given string.
 * @param {string} s - The input string from which IP addresses will be extracted.
 * @returns {string[]} A list of unique IP addresses found in the string.
 */
export function extractIps(s) {
  const ipPattern = /\b(?:\d{1,3}\.){3}\d{1,3}\b/g;
  const matches = s.match(ipPattern) || [];
  return Array.from(new Set(matches)); // Return unique IPs
}
