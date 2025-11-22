import { createUrl } from './url';

interface CheckProxyResponse {
  error: boolean;
  message: string;
  logFile: string | null;
}

export async function checkProxy(proxies: string) {
  const _httpsResponse: CheckProxyResponse = await fetch(createUrl('/php_backend/check-https-proxy.php'), {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ proxy: proxies }),
    credentials: 'include'
  })
    .then((res) => res.json())
    .catch((e) => {
      return { error: true, message: e.message, logFile: null };
    });
  const _httpResponse: CheckProxyResponse = await fetch(createUrl('/php_backend/check-http-proxy.php'), {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ proxy: proxies }),
    credentials: 'include'
  })
    .then((res) => res.json())
    .catch((e) => {
      return { error: true, message: e.message, logFile: null };
    });
  let buildMessage = 'Proxy check initiated\n';
  if (_httpsResponse?.message) {
    buildMessage += `[HTTPS] ${_httpsResponse.message} \n`;
  }
  if (_httpResponse?.message) {
    buildMessage += `[HTTP] ${_httpResponse.message}\n`;
  }
  return {
    error: _httpsResponse?.error || _httpResponse?.error || false,
    message: buildMessage.trim()
  };
}
