import React from 'react';
import { createUrl } from '../../utils/url';
import { getUserInfo } from '../../utils/user';

export async function getUserProxyLogUrl() {
  try {
    const data = await getUserInfo();
    if (data && (data as any).uid) {
      const logUrl = createUrl(`/php_backend/proxy-checker.php?id=${(data as any).uid}&type=log`);
      return logUrl;
    }
  } catch (_e) {
    // ignore
  }
}

async function fetchHttpsProxyCheckerResult(hash: string): Promise<string> {
  const url = createUrl('/php_backend/logs.php');
  const res = await fetch(`${url}?hash=check-https-proxy-${encodeURIComponent(hash)}`);
  if (!res.ok) {
    return `Failed to fetch https result: ${res.status} ${res.statusText}`;
  }
  return await res.text();
}

const LogViewer: React.FC = () => {
  const [loading, setLoading] = React.useState(false);
  const [log, setLog] = React.useState('');
  const [url, setUrl] = React.useState('');
  const [activeTab, setActiveTab] = React.useState<'log' | 'https'>('log');
  const [httpsHash, setHttpsHash] = React.useState('');
  const [httpsLog, setHttpsLog] = React.useState('');
  const [httpsLoading, setHttpsLoading] = React.useState(false);

  // On mount, fetch user id and set log URL
  React.useEffect(() => {
    (async () => {
      try {
        const data = await getUserInfo();
        if (data && (data as any).uid) {
          const uid = (data as any).uid;
          setHttpsHash(uid);
          const logUrl = createUrl(`/php_backend/proxy-checker.php?id=${uid}&type=log`);
          setUrl(logUrl);
        } else {
          setLog('Failed to retrieve user ID for log.');
        }
      } catch (_e) {
        setLog('Failed to retrieve user ID for log.');
      }
    })();
  }, []);

  // Poll log if url
  React.useEffect(() => {
    let interval: number | undefined;
    if (url) {
      const fetchLog = async () => {
        try {
          const res = await fetch(url);
          const text = await res.text();
          setLog((prev) => {
            if (prev.trim() === text.trim()) {
              return prev;
            }
            return text;
          });
        } catch {
          setLog('Failed to fetch log.');
        }
      };
      fetchLog();
      interval = window.setInterval(fetchLog, 3000);
    }
    return () => {
      if (interval) clearInterval(interval);
    };
  }, [url]);

  // Poll HTTPS result when httpsHash is available and https tab is active
  React.useEffect(() => {
    let interval: number | undefined;
    if (activeTab === 'https' && httpsHash) {
      const fetchHttps = async () => {
        setHttpsLoading(true);
        try {
          const text = await fetchHttpsProxyCheckerResult(httpsHash);
          setHttpsLog((prev) => {
            if (prev.trim() === text.trim()) return prev;
            return text;
          });
        } catch {
          setHttpsLog('Failed to fetch https result.');
        } finally {
          setHttpsLoading(false);
        }
      };
      fetchHttps();
      interval = window.setInterval(fetchHttps, 3000);
    }
    return () => {
      if (interval) clearInterval(interval);
    };
  }, [activeTab, httpsHash]);

  // HTTPS polling handled by effect below (auto-fetch when https tab active)

  const handleResetLog = async () => {
    setLoading(true);
    try {
      await fetch(createUrl('/php_backend/proxy-checker.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'resetLog=1'
      });
      // Refetch log after reset
      if (url) {
        const res = await fetch(url);
        const text = await res.text();
        setLog(text);
      }
    } catch {
      // Optionally show error
    }
    setLoading(false);
  };

  return (
    <section className="my-8">
      <div className="bg-white dark:bg-gray-900 rounded-xl shadow-lg border border-blue-200 dark:border-blue-700 p-6 transition-colors duration-300 flowbite-modal">
        <div className="flex justify-between items-center mb-4">
          <h2 className="text-lg font-bold text-blue-800 dark:text-blue-200 flex items-center gap-2">
            <i className="fa-duotone fa-file-lines"></i> Proxy Check Log
          </h2>
          <div className="flex gap-2">
            <button
              type="button"
              className="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-blue-600 dark:text-blue-200 bg-blue-50 dark:bg-blue-900 border border-blue-200 dark:border-blue-700 rounded-lg hover:bg-blue-100 dark:hover:bg-blue-800 transition disabled:opacity-50"
              onClick={handleResetLog}
              aria-label="Reset log"
              disabled={loading}>
              <i className="fa-duotone fa-rotate-left"></i> Reset Log
            </button>
          </div>
        </div>
        {/* Tabs */}
        <div className="mb-3">
          <ul className="flex -mb-px text-sm font-medium text-center text-gray-500 dark:text-gray-400" role="tablist">
            <li className="mr-2" role="presentation">
              <button
                type="button"
                role="tab"
                aria-selected={activeTab === 'log'}
                className={`inline-block p-2 rounded-t-lg border-b-2 ${activeTab === 'log' ? 'text-blue-600 border-blue-600 dark:text-blue-500 dark:border-blue-500 bg-gray-100 dark:bg-gray-800' : 'border-transparent hover:text-gray-600 dark:hover:text-gray-300'}`}
                onClick={() => setActiveTab('log')}>
                Checker Log
              </button>
            </li>
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
          </ul>
        </div>

        {/* Log panel */}
        <div className={`${activeTab === 'log' ? '' : 'hidden'}`}>
          <div className="mb-2 text-xs text-gray-600 dark:text-gray-300 break-all">
            <span className="font-mono text-xs text-gray-700 dark:text-gray-300">{url}</span>
          </div>
          <div className="border border-gray-200 dark:border-gray-700 rounded-lg bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-gray-100 p-3 h-64 overflow-auto font-mono text-xs whitespace-pre-wrap transition-colors duration-300">
            {loading ? (
              <span className="text-gray-400 dark:text-gray-500">Loading log...</span>
            ) : log ? (
              <span className="text-gray-800 dark:text-gray-100" dangerouslySetInnerHTML={{ __html: log }} />
            ) : (
              <span className="text-gray-400 dark:text-gray-500">No log output yet.</span>
            )}
          </div>
        </div>

        {/* HTTPS panel */}
        <div className={`${activeTab === 'https' ? '' : 'hidden'}`}>
          <div className="mb-2 flex gap-2 items-center">
            <span className="text-xs text-gray-500 dark:text-gray-400">Automatic HTTPS check for UID:</span>
            <span className="font-mono text-sm text-gray-900 dark:text-gray-100">{httpsHash || '—'}</span>
          </div>
          <div className="border border-gray-200 dark:border-gray-700 rounded-lg bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-gray-100 p-3 h-64 overflow-auto font-mono text-xs whitespace-pre-wrap transition-colors duration-300">
            {httpsLoading ? (
              <span className="text-gray-400 dark:text-gray-500">Fetching https result...</span>
            ) : httpsLog ? (
              <span className="text-gray-800 dark:text-gray-100" style={{ whiteSpace: 'pre-wrap' }}>
                {httpsLog}
              </span>
            ) : (
              <span className="text-gray-400 dark:text-gray-500">No https result fetched yet.</span>
            )}
          </div>
        </div>
      </div>
    </section>
  );
};

export default LogViewer;
