import { isValidProxy } from './utils.js';

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
 * Extracts IP:PORT pairs from a string, along with optional credentials (username and password).
 * Supports multiple formats, including standard `IP:PORT`, whitespace-separated, and JSON.
 * Filters and returns unique proxies, prioritizing those with credentials.
 *
 * @param {string|null} string - The input string containing IP:PORT pairs in various formats.
 * @returns {string[]} A list of unique proxies in `IP:PORT` format, including those with credentials (`username:password@IP:PORT`) if present.
 */
export function extractProxies(string) {
  if (!string || !string.trim()) {
    return [];
  }

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
      if (isValidProxy(proxy)) {
        results.push(proxy);
      }
    } else {
      results.push(match);
    }
  });

  // Handle unique proxies and prioritize those with credentials
  const uniqueProxies = {};

  results.forEach((proxy) => {
    if (proxy.includes('@')) {
      const [_credentials, address] = proxy.split('@');
      uniqueProxies[address] = proxy;
    } else if (!uniqueProxies[proxy]) {
      uniqueProxies[proxy] = proxy;
    }
  });

  return Object.values(uniqueProxies);
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
  return extractProxies(str)
    .map((str) => {
      if (!str.includes('@')) {
        // No credentials
        return {
          address: str,
          auth: undefined,
          username: undefined,
          password: undefined,
          ip: undefined,
          port: undefined
        };
      }

      const [auth, address] = str.split('@');
      if (!auth.includes(':')) {
        // Invalid auth format
        return {
          address: str,
          auth: undefined,
          username: undefined,
          password: undefined,
          ip: undefined,
          port: undefined
        };
      }

      const [username, password] = auth.split(':');
      if (!isValidProxy(address)) {
        // Invalid proxy format
        return null;
      }

      const [ip, port] = address.split(':');
      return { username, password, auth, address, ip, port };
    })
    .filter((o) => o !== null);
}

/**
 * Reads a file containing IP:PORT pairs and parses them.
 * @param {string} filename - The path to the file.
 * @returns {Promise<Proxy[]>} A list of parsed IP:PORT pairs.
 */
export async function extractProxiesFromFile(filename) {
  const fs = await import('fs/promises');
  try {
    const data = await fs.readFile(filename, 'utf-8');
    const lines = data.split('\n');
    const proxies = lines.flatMap((line) => extractProxies(line));
    return proxies;
  } catch (err) {
    console.error(`Failed to open ${filename}: ${err.message}`);
    return [];
  }
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
