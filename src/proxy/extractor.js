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

  const results = [];

  const ipPortPattern =
    /((?:(?:\d{1,3}\.){3}\d{1,3}):\d{2,5}(?:@\w+:\w+)?|(?:\w+:\w+@(?:\d{1,3}\.){3}\d{1,3}:\d{2,5}))/g;
  const matches1 = string.match(ipPortPattern) || [];

  const ipPortWhitespacePattern = /((?!0)\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\s+((?!0)\d{2,5})/g;
  const matches2 = Array.from(string.matchAll(ipPortWhitespacePattern));

  const ipPortJsonPattern = /"ip":"((?!0)\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})".*?"port":"((?!0)\d{2,5})"/g;
  const matches3 = Array.from(string.matchAll(ipPortJsonPattern));

  const matches = [...matches1, ...matches2, ...matches3];

  matches.forEach((match) => {
    if (typeof match === 'object' && match.length === 2) {
      const [ip, port] = match;
      const proxy = `${ip}:${port}`;
      if (isValidProxy(proxy)) results.push(proxy);
    } else if (typeof match === 'string') {
      results.push(match);
    }
  });

  // Handle unique proxies and prioritize those with credentials
  const uniqueProxies = {};

  results.forEach((proxy) => {
    if (proxy.includes('@')) {
      const [left, right] = proxy.split('@');
      // normalize: store address (IP:PORT) as key, keep original with credentials as value
      const address = left.includes(':') && left.indexOf('.') !== -1 ? left : right;
      uniqueProxies[address] = proxy;
    } else if (!uniqueProxies[proxy]) {
      uniqueProxies[proxy] = proxy;
    }
  });

  // Map to ProxyData instances
  return Object.values(uniqueProxies).map((p) => {
    // p could be: 'ip:port', 'ip:port@user:pass', 'user:pass@ip:port'
    const pd = new ProxyData();
    if (p.includes('@')) {
      const [left, right] = p.split('@');
      if (/^\d+\.\d+\.\d+\.\d+:\d+$/.test(left)) {
        // ip:port@user:pass
        pd.proxy = left;
        if (right.includes(':')) {
          const [username, password] = right.split(':');
          pd.username = username;
          pd.password = password;
        } else {
          pd.username = right;
        }
      } else if (/^\d+\.\d+\.\d+\.\d+:\d+$/.test(right)) {
        // user:pass@ip:port
        pd.proxy = right;
        if (left.includes(':')) {
          const [username, password] = left.split(':');
          pd.username = username;
          pd.password = password;
        } else {
          pd.username = left;
        }
      } else {
        // unknown, put raw
        pd.proxy = p;
      }
    } else {
      pd.proxy = p;
    }
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
