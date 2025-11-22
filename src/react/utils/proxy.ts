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
  const isError = _httpsResponse?.error || _httpResponse?.error || false;
  let buildMessage = !isError ? 'Proxy check initiated\n' : 'Proxy check encountered errors:\n';
  if (_httpsResponse?.message) {
    buildMessage += `${_httpsResponse.message} \n`;
  }
  if (_httpResponse?.message) {
    buildMessage += `${_httpResponse.message}\n`;
  }
  return {
    error: isError,
    message: buildMessage.trim()
  };
}
