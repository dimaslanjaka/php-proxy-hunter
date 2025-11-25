import { createUrl } from './url';

interface CheckProxyResponse {
  error: boolean;
  message: string;
  logFile: string | null;
}

async function checkProxyHttps(proxies: string): Promise<CheckProxyResponse> {
  return await fetch(createUrl('/php_backend/check-https-proxy.php'), {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ proxy: proxies }),
    credentials: 'include'
  })
    .then((res) => res.json())
    .catch((e) => {
      return { error: true, message: e.message, logFile: null };
    });
}

async function checkProxyHttp(proxies: string): Promise<CheckProxyResponse> {
  return await fetch(createUrl('/php_backend/check-http-proxy.php'), {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ proxy: proxies }),
    credentials: 'include'
  })
    .then((res) => res.json())
    .catch((e) => {
      return { error: true, message: e.message, logFile: null };
    });
}

export async function checkProxyType(proxies: string): Promise<CheckProxyResponse> {
  return await fetch(createUrl('/php_backend/check-proxy-type.php'), {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ proxy: proxies }),
    credentials: 'include'
  })
    .then((res) => res.json())
    .catch((e) => {
      return { error: true, message: e.message, logFile: null };
    });
}

export async function checkProxy(proxies: string) {
  const _httpsResponse = await checkProxyHttps(proxies);
  const _httpResponse = await checkProxyHttp(proxies);
  const _typeResponse = await checkProxyType(proxies);

  const isError = _httpsResponse?.error || _httpResponse?.error || _typeResponse?.error || false;
  let buildMessage = !isError ? 'Proxy check initiated\n' : 'Proxy check encountered errors:\n';
  if (_httpsResponse?.message) {
    buildMessage += `${_httpsResponse.message} \n`;
  }
  if (_httpResponse?.message) {
    buildMessage += `${_httpResponse.message}\n`;
  }
  if (_typeResponse?.message) {
    buildMessage += `${_typeResponse.message}\n`;
  }
  return {
    error: isError,
    message: buildMessage.trim()
  };
}
