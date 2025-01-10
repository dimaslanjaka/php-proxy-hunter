import { toMs } from '../../node_browser/puppeteer/time_utils.js';
import { getPublicIP } from '../proxy/utils.js';
import { buildCurl } from './curl.js';

const proxy = '174.75.211.222:4145';
const protocol = 'socks5';

async function main() {
  try {
    const myIp = await getPublicIP();
    const response = await buildCurl(
      'https://ifconfig.me/all.json',
      { protocol, address: proxy },
      toMs(60),
      'tmp/cookies/axios.txt'
    );

    let data = response.data;
    if (typeof data !== 'string') data = JSON.stringify(data);

    console.log('is working', data.includes(proxy.split(':')[0]));
    console.log('is matching device ip', data.includes(myIp));

    await buildCurl('https://google.com', { protocol, address: proxy }, toMs(60), 'tmp/cookies/axios.txt');
    await buildCurl('https://bing.com', { protocol, address: proxy }, toMs(60), 'tmp/cookies/axios.txt');
  } catch (e) {
    console.error(e.message, e.stack);
  }
}

main();
