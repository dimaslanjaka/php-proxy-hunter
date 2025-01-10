import axios from 'axios';
import { wrapper } from 'axios-cookiejar-support';
import { CookieJar } from 'tough-cookie';
import FileCookieStore from './axios.cookie.store.cjs';

const cookieFilePath = 'tmp/cookies/axios.txt';
const store = new FileCookieStore(cookieFilePath);
const jar = new CookieJar(store);

async function main() {
  // Wrap the axios instance with cookie support
  const client = wrapper(axios.create({ jar }));
  const url = 'https://bing.com';

  // Make the request
  await client.get(url);

  // Get cookies from the jar for the requested domain
  jar.getCookies(url, (err, cookies) => {
    if (err) {
      console.error('Error retrieving cookies:', err);
    } else {
      console.log('Cookies received:', cookies);
    }
  });
}

main();
