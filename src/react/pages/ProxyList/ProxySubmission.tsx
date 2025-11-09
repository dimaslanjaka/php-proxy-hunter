import React from 'react';
import { useTranslation } from 'react-i18next';
import { ReactFormSaver, ReactFormSaverRef } from 'jquery-form-saver/react';
import { extractProxies } from '../../../proxy/extractor';
import ProxyData from '../../../proxy/ProxyData';
import { createUrl } from '../../utils/url';
import { getUserProxyLogUrl } from './LogViewer';

async function _checkProxy(proxyData: ProxyData, type?: string) {
  const url = createUrl('/php_backend/proxy-checker.php');
  const result = await fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded'
    },
    body: new URLSearchParams({
      proxy: proxyData.proxy ?? '',
      type: type ?? '',
      username: proxyData.username ?? '',
      password: proxyData.password ?? ''
    })
  });
  const data = await result.json();
  console.log(data);
  return data;
}

async function _checkProxy2(proxies: string) {
  try {
    const resp = await fetch(createUrl('/php_backend/check-https-proxy.php'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ proxy: proxies }),
      credentials: 'include'
    });

    if (!resp.ok) {
      const text = await resp.text().catch(() => '');
      console.error('Error:', resp.status, resp.statusText, text);
      return;
    }

    const contentType = resp.headers.get('content-type') || '';
    const data = contentType.includes('application/json') ? await resp.json() : await resp.text();
    // handle the successful response if needed
    console.log('Response:', data);
  } catch (error) {
    console.error('Error:', error);
  }
}

export default function ProxySubmission() {
  const { t } = useTranslation();
  const [logUrl, setLogUrl] = React.useState('');
  const [statusUrl, setStatusUrl] = React.useState('');
  const [textarea, setTextarea] = React.useState('');
  const [proxyDatas, setProxyDatas] = React.useState<ProxyData[]>([]);
  const formSaverRef = React.useRef<ReactFormSaverRef | null>(null);

  React.useEffect(() => {
    // On mount, fetch user id and set log URL
    getUserProxyLogUrl().then((url) => {
      if (url) {
        setLogUrl(url);
        // Parse URL to change parameter 'type=log' to 'type=status'
        const statusUrl = url.replace('type=log', 'type=status');
        setStatusUrl(statusUrl);
      }
    });

    // restore saved form values (sync DOM -> React state for controlled textarea)
    formSaverRef.current?.restoreForm();
    const ta = document.querySelector<HTMLTextAreaElement>('textarea[name="proxies"]');
    if (ta && ta.value) {
      setTextarea(ta.value);
    }

    // auto save input,textarea,select elements
    // const elements = document.querySelectorAll('input,textarea,select');
    // debug to console.log
    // const show_debug = true;
    // if (show_debug) console.log(elements);
  }, []);

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    fetch(createUrl('/php_backend/proxy-add.php'), { method: 'POST', body: new URLSearchParams({ proxies: textarea }) })
      .then((res) => res.json())
      .then((data) => {
        console.log(data);
      })
      .catch((err) => {
        console.error(err);
      })
      .finally(() => {
        _checkProxy2(textarea);
      });

    // Split textarea into lines and extract proxies from each line
    const parsed = textarea.split(/\r?\n/).map(extractProxies).flat().filter(Boolean);

    const proxyDatas: ProxyData[] = [];
    // parsed is an array of proxies:
    // 103.160.204.144:80
    // 103.160.204.144:80@username:password
    // user:pass@103.160.204.144:80
    for (const proxy of parsed) {
      // Only support:
      // 1. host:port
      // 2. host:port@user:pass
      // 3. user:pass@host:port
      const icp = new ProxyData();
      if (proxy.includes('@')) {
        const [part1, part2] = proxy.split('@');
        // Check if part1 is user:pass or host:port
        if (/^\d+\.\d+\.\d+\.\d+:\d+$/.test(part1)) {
          // host:port@user:pass
          const [host, port] = part1.split(':');
          icp.proxy = `${host}:${port}`;
          if (part2.includes(':')) {
            const [username, password] = part2.split(':');
            icp.username = username;
            icp.password = password;
          } else {
            icp.username = part2;
          }
        } else if (/^[^:]+:[^:]+$/.test(part1) && /^\d+\.\d+\.\d+\.\d+:\d+$/.test(part2)) {
          // user:pass@host:port
          const [username, password] = part1.split(':');
          const [host, port] = part2.split(':');
          icp.proxy = `${host}:${port}`;
          icp.username = username;
          icp.password = password;
        } else {
          // Invalid format, skip
          continue;
        }
      } else {
        // host:port
        const parts = proxy.split(':');
        if (parts.length === 2) {
          const [host, port] = parts;
          icp.proxy = `${host}:${port}`;
        } else {
          // Invalid format, skip
          continue;
        }
      }
      proxyDatas.push(icp);
    }

    setProxyDatas(proxyDatas);
    handleProxyCheck();
  }

  function handleProxyCheck(type?: string) {
    const data = proxyDatas.shift();
    const types = type ? [type] : ['http', 'https', 'socks4', 'socks5', 'socks4a', 'socks5h'];
    if (data) {
      // Perform proxy check with the extracted data
      console.log(`Checking proxy: ${data}, Type: ${types.join(', ')}`);
    }
  }

  return (
    <section className="my-8">
      <div className="bg-white dark:bg-gray-900 rounded-xl shadow-lg border border-blue-200 dark:border-blue-700 p-6 transition-colors duration-300 flowbite-modal">
        <div className="flex justify-between items-center mb-4">
          <h2 className="text-lg font-bold text-blue-800 dark:text-blue-200 flex items-center gap-2">
            <i className="fa-duotone fa-paper-plane"></i> Proxy Submission
          </h2>
          <div className="flex gap-2">
            <button
              type="button"
              className="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-blue-600 dark:text-blue-200 bg-blue-50 dark:bg-blue-900 border border-blue-200 dark:border-blue-700 rounded-lg hover:bg-blue-100 dark:hover:bg-blue-800 transition-colors"
              title={t('populate_with_sample_proxies')}
              onClick={() =>
                setTextarea(`103.160.204.144:80\n103.160.204.144:80@username:password\nuser:pass@103.160.204.144:80`)
              }>
              <i className="fa-duotone fa-wand-magic-sparkles"></i> Populate
            </button>
          </div>
        </div>
        <ReactFormSaver ref={formSaverRef} storagePrefix="proxy-submission" className="mb-4" onSubmit={handleSubmit}>
          <div className="mb-1">
            <label htmlFor="proxyTextarea" className="block text-sm font-medium text-gray-700 dark:text-gray-200 mr-2">
              Proxies
            </label>
          </div>
          <textarea
            id="proxyTextarea"
            name="proxies"
            rows={4}
            className="w-full p-2 border border-gray-300 dark:border-gray-700 rounded-lg bg-gray-50 dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-100 mb-2"
            placeholder={`103.160.204.144:80\n103.160.204.144:80@username:password\nuser:pass@103.160.204.144:80`}
            value={textarea}
            onChange={(e) => setTextarea(e.target.value)}
          />
          <button
            type="submit"
            className="inline-flex items-center gap-1 px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors">
            <i className="fa-duotone fa-paper-plane"></i> Submit
          </button>
        </ReactFormSaver>
        <div className="mb-2 text-xs text-gray-600 dark:text-gray-300 break-all">
          <span className="font-mono">{logUrl}</span>
        </div>
        <div className="mb-2 text-xs text-gray-600 dark:text-gray-300 break-all">
          <span className="font-mono">{statusUrl}</span>
        </div>
        {/* Add more UI elements as needed */}
      </div>
    </section>
  );
}
