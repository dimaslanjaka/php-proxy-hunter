import axios from 'axios';
import fs from 'fs';
import { CookieJar } from 'tough-cookie';
import { promisify } from 'util';
import { isNotEmpty } from './string.js';

/**
 * Reads and parses cookies from a Netscape cookie file.
 *
 * @param {string} filePath - The path to the cookie file.
 * @returns {Record<string, any>} An object representing the cookies, organized by domain and name.
 */
export function readCookiesFromFile(filePath) {
  if (!fs.existsSync(filePath)) return {};
  const cookies = {};
  const content = fs.readFileSync(filePath, 'utf-8');
  const lines = content.split('\n');

  // Validate the file format
  const magicLine = lines[0].trim();
  if (!/^# Netscape HTTP Cookie File/.test(magicLine) && !/^# HttpOnly/.test(magicLine)) {
    throw new Error(`${filePath} does not look like a Netscape cookies file`);
  }

  for (const line of lines) {
    const trimmedLine = line.trim();
    if (trimmedLine.length === 0 || trimmedLine.startsWith('#')) {
      continue;
    }

    // Handle HttpOnly lines
    let httpOnly = false;
    let cookieLine = trimmedLine;

    if (trimmedLine.startsWith('#HttpOnly_')) {
      httpOnly = true;
      cookieLine = trimmedLine.replace(/^#HttpOnly_/, '');
    }

    const parts = cookieLine.split('\t');
    if (parts.length !== 7) {
      throw new Error(`Invalid cookie format at line: ${trimmedLine}`);
    }

    const domain = parts[0];
    const path = parts[2];
    const secure = parts[3] === 'TRUE';
    const expires = parseInt(parts[4]) ? new Date(parts[4] * 1000) : 'Infinity';
    const name = decodeURIComponent(parts[5]);
    const value = decodeURIComponent(parts[6]);

    if (!cookies[domain]) {
      cookies[domain] = {};
    }

    cookies[domain][name] = {
      value,
      path,
      secure,
      expires,
      httpOnly
    }; // Store cookie attributes
  }
  return cookies;
}

/**
 * Writes cookies back to a Netscape cookie file in the correct format.
 *
 * @param {string} filePath - The path to the cookie file where cookies will be saved.
 * @param {Record<string, any>} cookies - An object representing the cookies, organized by domain and name.
 */
export function writeCookiesToFile(filePath, cookies) {
  let content = '# Netscape HTTP Cookie File\n';
  content += '# http://curl.haxx.se/rfc/cookie_spec.html\n';
  content += '# This is a generated file!  Do not edit.\n\n'; // Header comments

  for (const [domain, domainCookies] of Object.entries(cookies)) {
    for (const [name, value] of Object.entries(domainCookies)) {
      // Construct the Netscape format for each cookie
      content += `${domain}\tTRUE\t/\tFALSE\t0\t${name}\t${value}\n`;
    }
  }

  fs.writeFileSync(filePath, content, 'utf-8');
}

const agentCache = new Map();

/**
 * Get/create agent
 * @param {{ protocol: "http"|"https"|"socks4"|"socks5", address: string, username: string | null | undefined, password: string | null | undefined }} data
 * @param {number} timeout
 * @returns {Promise<import("https-proxy-agent").HttpsProxyAgent<string> | import("socks-proxy-agent").SocksProxyAgent>}
 */
export const getProxyAgent = async (data, timeout = 60 * 1000) => {
  const proxyHost = data.address.split(':')[0];
  const proxyPort = data.address.split(':')[1];
  const key = `${data.protocol}-${proxyHost}:${proxyPort}`;
  if (agentCache.has(key)) return agentCache.get(key);

  let agent;
  const agentOptions = { rejectUnauthorized: false, timeout };

  if (data.protocol === 'https' || data.protocol === 'http') {
    const proxyUrl = `http://${proxyHost}:${proxyPort}`;
    const HttpsProxyAgent = await import('https-proxy-agent').then((lib) => lib.HttpsProxyAgent);
    if (isNotEmpty(data.username) && isNotEmpty(data.password)) {
      agentOptions.auth = `${data.username}:${data.password}`;
    }
    agent = new HttpsProxyAgent(proxyUrl, agentOptions);
  } else if (data.protocol === 'socks5' || data.protocol === 'socks4') {
    let proxyUrl = `${data.protocol}://${proxyHost}:${proxyPort}`;
    if (isNotEmpty(data.username) && isNotEmpty(data.password)) {
      proxyUrl = `${data.protocol}://${data.username}:${data.password}@${proxyHost}:${proxyPort}`;
    }
    const SocksProxyAgent = await import('socks-proxy-agent').then((lib) => lib.SocksProxyAgent);
    agent = new SocksProxyAgent(proxyUrl, agentOptions);
  }

  agentCache.set(key, agent);
  return agent;
};

/**
 * @param {{ protocol: "http"|"https"|"socks4"|"socks5", address: string, username: string | null | undefined, password: string | null | undefined }} [proxy=null] - The proxy in IP:PORT format (e.g., '192.168.0.1:8080').
 * @param {string} url
 * @param {number} [timeout=60000] - Request timeout in milliseconds.
 * @param {string|null} [cookieFilePath=null] - Path to the cookie file.
 * @returns {Promise<import("axios").AxiosResponse>}
 */
export const buildCurl = async (url, proxy = null, timeout = 60 * 1000, cookieFilePath = null) => {
  if (!cookieFilePath) cookieFilePath = 'tmp/cookies/default.txt';

  // Import custom cookie store
  const FileCookieStore = await import('./axios.cookie.store.cjs').then((lib) => lib.default);

  // Create a tough-cookie jar with the custom file store
  const store = new FileCookieStore(cookieFilePath);
  const jar = new CookieJar(store);

  // Promisify jar methods to handle async operations
  const getCookieString = promisify(jar.getCookieString.bind(jar));
  const setCookie = promisify(jar.setCookie.bind(jar));

  // Get appropriate proxy agent based on protocol
  let agent = await getProxyAgent(proxy, timeout);

  // Get cookies for the current URL
  const cookieString = await getCookieString(url);

  // Make the HTTP request with axios
  const response = await axios.get(url, {
    httpAgent: agent,
    httpsAgent: agent,
    timeout,
    insecureHTTPParser: true, // Optional, depending on your use case
    maxRedirects: 10,
    headers: {
      'Accept-Language': 'en-US,en;q=0.9',
      'User-Agent':
        'Mozilla/5.0 (Android 14; Mobile; wv) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/111.0.0.0 Mobile Safari/537.36',
      Cookie: cookieString // Manually set cookies in the headers
    }
  });

  // Update the cookie jar with any new cookies from the response
  const setCookieHeader = response.headers['set-cookie'];
  if (setCookieHeader) {
    await Promise.all(setCookieHeader.map((cookie) => setCookie(cookie, url)));
  }

  return response;
};
