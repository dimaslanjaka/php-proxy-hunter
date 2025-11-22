import { noop } from '../../utils/other';
import { createUrl } from './url';

export async function checkProxy(proxies: string) {
  await fetch(createUrl('/php_backend/check-https-proxy.php'), {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ proxy: proxies }),
    credentials: 'include'
  }).catch(noop);
  await fetch(createUrl('/php_backend/check-http-proxy.php'), {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ proxy: proxies }),
    credentials: 'include'
  }).catch(noop);
}
