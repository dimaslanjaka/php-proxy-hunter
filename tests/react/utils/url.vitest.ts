import { describe, it, beforeAll, afterAll, expect, vi } from 'vitest';
import { createUrl } from '../../../src/react/utils/url';
import * as utilsIndex from '../../../src/react/utils/index';

let { isViteDevServer, viteBaseUrl } = utilsIndex;

describe('createUrl', () => {
  let OLD_LOCATION: Location;

  beforeAll(() => {
    // Mock imported isViteDevServer and viteBaseUrl for Vitest
    vi.spyOn(utilsIndex, 'isViteDevServer', 'get').mockImplementation(() => isViteDevServer ?? false);
    vi.spyOn(utilsIndex, 'viteBaseUrl', 'get').mockImplementation(() => viteBaseUrl ?? '/');
    // Save original location
    OLD_LOCATION = window.location;
    // @ts-expect-error: to allow overriding window.location in tests
    delete window.location;
    // @ts-expect-error: window.location is read-only in TypeScript types, but we need to override for test
    window.location = {
      ...OLD_LOCATION,
      origin: 'https://example.com'
    };
  });

  afterAll(() => {
    // Restore original location
    // @ts-expect-error: window.location is read-only in TypeScript types, but we need to restore for test
    window.location = OLD_LOCATION;
  });

  it('should append index.html if path ends with /', () => {
    expect(createUrl('/foo/')).toContain('foo/index.html');
  });

  it('should use current origin for non-PHP paths', () => {
    expect(createUrl('/bar')).toContain('https://example.com/bar');
  });

  it('should append query params', () => {
    const url = createUrl('/baz', { a: 1, b: 'test' });
    expect(url).toContain('a=1');
    expect(url).toContain('b=test');
  });

  it('should use viteBaseUrl if set and not PHP', () => {
    viteBaseUrl = '/base/';
    expect(createUrl('/abc')).toContain('/base/abc');
    viteBaseUrl = '';
  });

  it('should use backend hostname for PHP files', () => {
    isViteDevServer = true;
    expect(createUrl('/foo.php', {}, { backendDev: 'devhost', backendProd: 'prodhost' })).toContain(
      'https://devhost/foo.php'
    );
    isViteDevServer = false;
    expect(createUrl('/foo.php', {}, { backendDev: 'devhost', backendProd: 'prodhost' })).toContain(
      'https://prodhost/foo.php'
    );
  });
});
