import React from 'react';
import { createUrl } from '../../utils/url';
import { getUserInfo } from '../../utils/user';

// helper kept for compatibility if used elsewhere
export async function getUserProxyLogUrl() {
  try {
    const data = await getUserInfo();
    if (data && (data as any).uid) {
      // previously pointed to php_backend/proxy-checker.php; return logs.php endpoint instead
      const uid = (data as any).uid;
      const logUrl = createUrl(`/php_backend/logs.php?hash=check-https-proxy/${encodeURIComponent(uid)}`);
      return logUrl;
    }
  } catch (_e) {
    // ignore
  }
}

async function fetchHttpsProxyCheckerResult(hash: string): Promise<string> {
  const url = createUrl('/php_backend/logs.php');
  const res = await fetch(`${url}?hash=check-https-proxy/${encodeURIComponent(hash)}`);
  if (!res.ok) {
    return `Failed to fetch https result: ${res.status} ${res.statusText}`;
  }
  return await res.text();
}

async function fetchHttpProxyCheckerResult(hash: string): Promise<string> {
  const url = createUrl('/php_backend/logs.php');
  const res = await fetch(`${url}?hash=check-http-proxy/${encodeURIComponent(hash)}`);
  if (!res.ok) {
    return `Failed to fetch http result: ${res.status} ${res.statusText}`;
  }
  return await res.text();
}

async function fetchProxyTypeDetectionResult(hash: string): Promise<string> {
  const url = createUrl('/php_backend/logs.php');
  const res = await fetch(`${url}?hash=check-proxy-type/${encodeURIComponent(hash)}`);
  if (!res.ok) {
    return `Failed to fetch proxy type result: ${res.status} ${res.statusText}`;
  }
  return await res.text();
}

const LogViewer: React.FC = () => {
  // no longer using the proxy-checker.php log reset; only poll logs.php
  const [activeTab, setActiveTab] = React.useState<'https' | 'http' | 'type'>('https');
  const [hash, setHash] = React.useState('');
  const [httpsLog, setHttpsLog] = React.useState('');
  const [httpLog, setHttpLog] = React.useState('');
  const [typeLog, setTypeLog] = React.useState('');

  // On mount, fetch user id to use with logs endpoints
  React.useEffect(() => {
    (async () => {
      try {
        const data = await getUserInfo();
        if (data && (data as any).uid) {
          const uid = (data as any).uid;
          setHash(uid);
        }
      } catch (_e) {
        // ignore
      }
    })();
  }, []);

  // Poll HTTPS result when httpsHash is available and https tab is active
  React.useEffect(() => {
    let interval: number | undefined;
    if (activeTab === 'https' && hash) {
      const fetchHttps = async () => {
        try {
          const text = await fetchHttpsProxyCheckerResult(hash);
          setHttpsLog((prev) => {
            if (prev.trim() === text.trim()) return prev;
            return text;
          });
        } catch {
          setHttpsLog('Failed to fetch https result.');
        }
      };
      fetchHttps();
      interval = window.setInterval(fetchHttps, 3000);
    }
    return () => {
      if (interval) clearInterval(interval);
    };
  }, [activeTab, hash]);

  // Poll HTTP result when httpHash is available and http tab is active
  React.useEffect(() => {
    let interval: number | undefined;
    if (activeTab === 'http' && hash) {
      const fetchHttp = async () => {
        try {
          const text = await fetchHttpProxyCheckerResult(hash);
          setHttpLog((prev) => {
            if (prev.trim() === text.trim()) return prev;
            return text;
          });
        } catch {
          setHttpLog('Failed to fetch http result.');
        }
      };
      fetchHttp();
      interval = window.setInterval(fetchHttp, 3000);
    }
    return () => {
      if (interval) clearInterval(interval);
    };
  }, [activeTab, hash]);

  // Poll proxy type detection result when available and type tab is active
  React.useEffect(() => {
    let interval: number | undefined;
    if (activeTab === 'type' && hash) {
      const fetchType = async () => {
        try {
          const text = await fetchProxyTypeDetectionResult(hash);
          setTypeLog((prev) => {
            if (prev.trim() === text.trim()) return prev;
            return text;
          });
        } catch {
          setTypeLog('Failed to fetch proxy type result.');
        }
      };
      fetchType();
      interval = window.setInterval(fetchType, 3000);
    }
    return () => {
      if (interval) clearInterval(interval);
    };
  }, [activeTab, hash]);

  return (
    <section className="my-8">
      <div className="bg-white dark:bg-gray-900 rounded-xl shadow-lg dark:shadow-white border border-blue-200 dark:border-blue-700 p-6 transition-colors duration-300 flowbite-modal">
        <div className="flex justify-between items-center mb-4">
          <h2 className="text-lg font-bold text-blue-800 dark:text-blue-200 flex items-center gap-2">
            <i className="fa-duotone fa-file-lines"></i> Proxy Check Results
          </h2>
        </div>
        {/* Tabs */}
        <div className="mb-3">
          <ul className="flex -mb-px text-sm font-medium text-center text-gray-500 dark:text-gray-400" role="tablist">
            <li role="presentation">
              <button
                type="button"
                role="tab"
                aria-selected={activeTab === 'https'}
                className={`inline-block p-2 rounded-t-lg border-b-2 ${activeTab === 'https' ? 'text-blue-600 border-blue-600 dark:text-blue-500 dark:border-blue-500 bg-gray-100 dark:bg-gray-800' : 'border-transparent hover:text-gray-600 dark:hover:text-gray-300'}`}
                onClick={() => setActiveTab('https')}>
                HTTPS check
              </button>
            </li>
            <li role="presentation">
              <button
                type="button"
                role="tab"
                aria-selected={activeTab === 'http'}
                className={`inline-block p-2 rounded-t-lg border-b-2 ${activeTab === 'http' ? 'text-blue-600 border-blue-600 dark:text-blue-500 dark:border-blue-500 bg-gray-100 dark:bg-gray-800' : 'border-transparent hover:text-gray-600 dark:hover:text-gray-300'}`}
                onClick={() => setActiveTab('http')}>
                HTTP check
              </button>
            </li>
            <li role="presentation">
              <button
                type="button"
                role="tab"
                aria-selected={activeTab === 'type'}
                className={`inline-block p-2 rounded-t-lg border-b-2 ${activeTab === 'type' ? 'text-blue-600 border-blue-600 dark:text-blue-500 dark:border-blue-500 bg-gray-100 dark:bg-gray-800' : 'border-transparent hover:text-gray-600 dark:hover:text-gray-300'}`}
                onClick={() => setActiveTab('type')}>
                Type detection
              </button>
            </li>
          </ul>
        </div>

        {/* The old 'Checker Log' tab was removed because php_backend/proxy-checker.php no longer exists. */}

        {/* HTTPS panel */}
        <div className={`${activeTab === 'https' ? '' : 'hidden'}`}>
          <div className="mb-2">
            <div className="flex flex-col sm:flex-row sm:items-center gap-1">
              <span className="text-xs text-gray-500 dark:text-gray-400">Logs HTTPS check for UID:</span>
              <span className="font-mono text-sm text-gray-900 dark:text-gray-100 break-words max-w-full overflow-auto">
                {hash || '—'}
              </span>
            </div>
          </div>
          <div className="border border-gray-200 dark:border-gray-700 rounded-lg bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-gray-100 p-3 h-64 overflow-auto font-mono text-xs whitespace-pre-wrap transition-colors duration-300">
            {httpsLog ? (
              <span className="text-gray-800 dark:text-gray-100" style={{ whiteSpace: 'pre-wrap' }}>
                {httpsLog}
              </span>
            ) : (
              <span className="text-gray-400 dark:text-gray-500">No https result fetched yet.</span>
            )}
          </div>
        </div>

        {/* HTTP panel */}
        <div className={`${activeTab === 'http' ? '' : 'hidden'}`}>
          <div className="mb-2">
            <div className="flex flex-col sm:flex-row sm:items-center gap-1">
              <span className="text-xs text-gray-500 dark:text-gray-400">Logs HTTP check for UID:</span>
              <span className="font-mono text-sm text-gray-900 dark:text-gray-100 break-words max-w-full overflow-auto">
                {hash || '—'}
              </span>
            </div>
          </div>
          <div className="border border-gray-200 dark:border-gray-700 rounded-lg bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-gray-100 p-3 h-64 overflow-auto font-mono text-xs whitespace-pre-wrap transition-colors duration-300">
            {httpLog ? (
              <span className="text-gray-800 dark:text-gray-100" style={{ whiteSpace: 'pre-wrap' }}>
                {httpLog}
              </span>
            ) : (
              <span className="text-gray-400 dark:text-gray-500">No http result fetched yet.</span>
            )}
          </div>
        </div>

        {/* Proxy type detection panel */}
        <div className={`${activeTab === 'type' ? '' : 'hidden'}`}>
          <div className="mb-2">
            <div className="flex flex-col sm:flex-row sm:items-center gap-1">
              <span className="text-xs text-gray-500 dark:text-gray-400">Logs proxy type detection for UID:</span>
              <span className="font-mono text-sm text-gray-900 dark:text-gray-100 break-words max-w-full overflow-auto">
                {hash || '—'}
              </span>
            </div>
          </div>
          <div className="border border-gray-200 dark:border-gray-700 rounded-lg bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-gray-100 p-3 h-64 overflow-auto font-mono text-xs whitespace-pre-wrap transition-colors duration-300">
            {typeLog ? (
              <span className="text-gray-800 dark:text-gray-100" style={{ whiteSpace: 'pre-wrap' }}>
                {typeLog}
              </span>
            ) : (
              <span className="text-gray-400 dark:text-gray-500">No proxy type result fetched yet.</span>
            )}
          </div>
        </div>
      </div>
    </section>
  );
};

export default LogViewer;
