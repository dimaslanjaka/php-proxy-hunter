import fs from 'fs';
import { Cookie, CookieJar } from 'tough-cookie';

class NetscapeCookieStore {
  constructor(filePath) {
    this.filePath = filePath;
    this.jar = new CookieJar();
    this.loadCookies();
  }

  loadCookies() {
    if (fs.existsSync(this.filePath)) {
      const data = fs.readFileSync(this.filePath, 'utf8');
      const lines = data.split('\n');
      for (const line of lines) {
        const trimmedLine = line.trim();
        if (trimmedLine.startsWith('#') || !trimmedLine) {
          continue; // Skip comments and empty lines
        }

        const [domain, _flag, path, secure, expiration, name, value] = trimmedLine.split('\t');
        const cookie = new Cookie({
          key: name,
          value: value,
          domain: domain,
          path: path,
          secure: secure === 'TRUE',
          expires: new Date(expiration * 1000)
        });

        this.jar.setCookie(cookie);
      }
    }
  }

  saveCookies() {
    const cookies = this.jar.getCookiesSync();
    const lines = [];
    lines.push('# Netscape HTTP Cookie File');
    lines.push('# http://curl.haxx.se/rfc/cookie_spec.html');
    lines.push('# This file was generated by tough-cookie');

    for (const cookie of cookies) {
      lines.push(
        `${cookie.domain}\t${cookie.secure ? 'TRUE' : 'FALSE'}\t${cookie.path}\t${cookie.secure ? 'TRUE' : 'FALSE'}\t${Math.floor(cookie.expires.getTime() / 1000)}\t${cookie.key}\t${cookie.value}`
      );
    }

    fs.writeFileSync(this.filePath, lines.join('\n'), 'utf8');
  }

  async getCookie(url) {
    const cookie = await this.jar.getCookieString(url);
    return cookie;
  }

  async setCookie(url, cookie) {
    await this.jar.setCookie(cookie, url);
    this.saveCookies();
  }
}

// Usage
const cookieStore = new NetscapeCookieStore('tmp/cookies/axios.txt');

// Set a cookie
await cookieStore.setCookie(
  'http://example.com',
  'name=value; Domain=example.com; Path=/; Expires=Wed, 21 Oct 2025 07:28:00 GMT;'
);

// Get a cookie
const cookie = await cookieStore.getCookie('http://example.com');
console.log(cookie);
