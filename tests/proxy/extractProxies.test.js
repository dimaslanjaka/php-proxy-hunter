import { extractProxies } from '../../src/proxy/extractor.js';

describe('extractProxies', () => {
  test('ip:port only', () => {
    const input = '8.8.8.8:8080';
    const res = extractProxies(input);
    expect(Array.isArray(res)).toBe(true);
    expect(res.some((p) => p.proxy === '8.8.8.8:8080')).toBe(true);
    // No credentials expected
    expect(res.every((p) => !(p.username && p.password))).toBe(true);
  });

  test('ip:port with auth suffix', () => {
    const input = '147.75.68.200:10098@ProxyUser:ProxyPass';
    const res = extractProxies(input);
    expect(Array.isArray(res)).toBe(true);
    expect(
      res.some((p) => p.proxy === '147.75.68.200:10098' && p.username === 'ProxyUser' && p.password === 'ProxyPass')
    ).toBe(true);
  });

  test('auth prefix user:pass@ip:port', () => {
    const input = 'ProxyUser:ProxyPass@147.75.68.200:10098';
    const res = extractProxies(input);
    expect(Array.isArray(res)).toBe(true);
    expect(
      res.some((p) => p.proxy === '147.75.68.200:10098' && p.username === 'ProxyUser' && p.password === 'ProxyPass')
    ).toBe(true);
  });

  test('json ip/port without creds', () => {
    const input = '{"ip": "147.75.68.200","port":"10098"}';
    const res = extractProxies(input);
    expect(Array.isArray(res)).toBe(true);
    expect(res.some((p) => p.proxy === '147.75.68.200:10098')).toBe(true);
    expect(res.every((p) => !(p.username && p.password))).toBe(true);
  });

  test('json ip/port with user/pass', () => {
    const input = '{"ip": "147.75.68.200","port":"10098", "user":"ProxyUser", "pass":"ProxyPass"}';
    const res = extractProxies(input);
    expect(Array.isArray(res)).toBe(true);
    expect(
      res.some((p) => p.proxy === '147.75.68.200:10098' && p.username === 'ProxyUser' && p.password === 'ProxyPass')
    ).toBe(true);
  });

  test('json proxy field', () => {
    const input = '{"proxy": "147.75.68.200:10098"}';
    const res = extractProxies(input);
    expect(Array.isArray(res)).toBe(true);
    expect(res.some((p) => p.proxy === '147.75.68.200:10098')).toBe(true);
  });
});
