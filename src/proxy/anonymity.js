import Bluebird from 'bluebird';
import _ from 'lodash';
import { buildCurl } from '../utils/curl.js';
import { filterWorkingUrls } from '../utils/url.js';
import { getPublicIP } from './utils.js';

const proxy_judges = [
  'https://wfuchs.de/azenv.php',
  'http://mojeip.net.pl/asdfa/azenv.php',
  'http://httpheader.net/azenv.php',
  'http://pascal.hoez.free.fr/azenv.php',
  'https://www.cooleasy.com/azenv.php',
  'http://azenv.net/',
  'http://sh.webmanajemen.com/data/azenv.php'
];

/**
 * Obtain the anonymity of the proxy.
 * @param {string} response - The response from the proxy judge.
 * @returns {string} - "Transparent", "Anonymous", "Elite" or an empty string for failed anonymity check.
 */
export function parseAnonymity(response) {
  const ip = getPublicIP();

  if (ip === '') {
    return '';
  }

  if (response.includes(ip)) {
    // Device IP is found in proxy judge's response headers
    return 'Transparent';
  }

  const privacyHeaders = [
    'VIA',
    'X-FORWARDED-FOR',
    'X-FORWARDED',
    'FORWARDED-FOR',
    'FORWARDED-FOR-IP',
    'FORWARDED',
    'CLIENT-IP',
    'PROXY-CONNECTION'
  ];

  if (privacyHeaders.some((header) => response.includes(header))) {
    // Response contains specific privacy-related headers
    return 'Anonymous';
  }

  // If no IP found and no privacy headers, it's elite
  return 'Elite';
}

/**
 * get anonymity of proxy
 * @param {string} proxy
 */
export async function getAnonymity(proxy) {
  const load = await import('cheerio').then((lib) => lib.load);
  const workingUrls = _.shuffle(await filterWorkingUrls(proxy_judges));

  for (let i = 0; i < workingUrls.length; i++) {
    const url = workingUrls[i];

    const results = await Bluebird.all(
      ['http', 'socks4', 'socks5'].map(async (protocol) => {
        try {
          const response = await buildCurl(url, { protocol, address: proxy });
          return { protocol, data: response.data }; // Store protocol along with data
        } catch (_) {
          return { protocol, data: null };
        }
      })
    ).filter((o) => o.data !== null);

    // Filter responses where data is valid HTML containing <title>
    const validResponses = results.filter(({ data }) => typeof data === 'string' && data.includes('<title'));

    if (validResponses.length > 0) {
      const anonymities = validResponses
        .map(({ data, protocol }) => {
          const $ = load(data);
          const title = $('title').text();
          if (typeof title === 'string' && title.toLowerCase().includes('AZ Environment'.toLowerCase())) {
            return { protocol, anonymity: parseAnonymity(data) }; // Return protocol and anonymity
          }
          return null; // Return null if no valid title found
        })
        .filter((result) => result !== null); // Filter out null values

      // Example output: [{ protocol: 'http', anonymity: 'Elite' }, { protocol: 'socks5', anonymity: 'Transparent' }]
      // Exit loop after first valid response
      return anonymities;
    }
  }
}

// getAnonymity("198.7.56.73:36685").then(console.log);
