import { createUrl } from './url';
import { postJson } from './ajax-helper';

interface CheckProxyResponse {
  error: boolean;
  message: string;
  logFile: string | null;
}

export async function checkProxyHttps(proxies: string): Promise<CheckProxyResponse> {
  try {
    const res = await postJson<CheckProxyResponse>(createUrl('/php_backend/executor.php'), {
      proxy: proxies,
      file: '/php_backend/check-https-proxy.php'
    });
    return res;
  } catch (e: any) {
    return { error: true, message: e?.message || String(e), logFile: null };
  }
}

export async function checkProxyHttp(proxies: string): Promise<CheckProxyResponse> {
  try {
    const res = await postJson<CheckProxyResponse>(createUrl('/php_backend/check-http-proxy.php'), {
      proxy: proxies
    });
    return res;
  } catch (e: any) {
    return { error: true, message: e?.message || String(e), logFile: null };
  }
}

export async function checkProxyType(proxies: string): Promise<CheckProxyResponse> {
  try {
    const res = await postJson<CheckProxyResponse>(createUrl('/php_backend/check-proxy-type.php'), {
      proxy: proxies
    });
    return res;
  } catch (e: any) {
    return { error: true, message: e?.message || String(e), logFile: null };
  }
}

export async function checkOldProxy(): Promise<CheckProxyResponse> {
  try {
    const res = await postJson<CheckProxyResponse>(createUrl('/php_backend/check-old-proxy.php'), {});
    return res;
  } catch (e: any) {
    return { error: true, message: e?.message || String(e), logFile: null };
  }
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
