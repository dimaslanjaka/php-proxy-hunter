import React from 'react';
import { createUrl } from '../../utils/url';
import { getUserInfo } from '../../utils/user';
import { get } from '../../utils/ajax-helper';
import { convertAnsiToHtml } from '../../utils/ansi-to-html';

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

async function fetchCheckOldProxyResult(hash: string): Promise<string> {
  const url = createUrl('/php_backend/logs.php');
  try {
    const text = await get<string>(`${url}?hash=check-old-proxy/${encodeURIComponent(hash)}`, {
      responseType: 'text'
    } as any);
    return text || '';
  } catch (err: any) {
    if (err && err.response) {
      return `Failed to fetch check old proxy result: ${err.response.status} ${err.response.statusText || ''}`;
    }
    return `Failed to fetch check old proxy result: ${String(err)}`;
  }
}

const LogViewer: React.FC = () => {
  // no longer using the proxy-checker.php log reset; only poll logs.php
  const [activeTab, setActiveTab] = React.useState<'old' | 'executor'>('executor');
  const [hash, setHash] = React.useState('');
  const [oldLog, setOldLog] = React.useState('');
  const [executorFiles, setExecutorFiles] = React.useState<Array<any>>([]);
  const [executorIndex, setExecutorIndex] = React.useState<number | null>(null);
  const [executorLog, setExecutorLog] = React.useState('');

  // ANSI→HTML conversion is provided by shared utility
  React.useEffect(() => {
    let isMounted = true;

    const fetchData = async () => {
      try {
        const [userData, logsRes] = await Promise.all([
          getUserInfo(),
          get(createUrl('/php_backend/logs.php', { executor: 1 }))
        ]);

        // handle user
        if (isMounted && userData?.uid) {
          setHash(userData.uid);
        }

        // handle logs
        if (isMounted && Array.isArray(logsRes)) {
          setExecutorFiles(logsRes as Array<any>);
        }
      } catch (_e) {
        // ignore failures
      }
    };

    fetchData();

    return () => {
      isMounted = false;
    };
  }, []);

  // Poll executor log when an executor file is selected
  React.useEffect(() => {
    let interval: number | undefined;
    if (activeTab === 'executor' && executorIndex !== null && executorFiles[executorIndex]) {
      const filename = executorFiles[executorIndex].name;
      const fetchExec = async () => {
        try {
          const text = await get<string>(createUrl('/php_backend/logs.php', { executor: 1, file: filename }), {
            responseType: 'text'
          } as any);
          if (typeof text !== 'string') {
            setExecutorLog('Failed to fetch executor log.');
            return;
          }
          setExecutorLog((prev) => {
            if (prev.trim() === text.trim()) return prev;
            return text;
          });
        } catch (_err) {
          setExecutorLog('Failed to fetch executor log.');
        }
      };
      fetchExec();
      interval = window.setInterval(fetchExec, 3000);
    }
    return () => {
      if (interval) clearInterval(interval);
    };
  }, [activeTab, executorIndex, executorFiles]);

  // Poll check old proxy result when available and old tab is active
  React.useEffect(() => {
    let interval: number | undefined;
    if (activeTab === 'old' && hash) {
      const fetchOld = async () => {
        try {
          const text = await fetchCheckOldProxyResult(hash);
          setOldLog((prev) => {
            if (prev.trim() === text.trim()) return prev;
            return text;
          });
        } catch {
          setOldLog('Failed to fetch check old proxy result.');
        }
      };
      fetchOld();
      interval = window.setInterval(fetchOld, 3000);
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
            {/* Removed default tabs: HTTPS, HTTP, Type detection */}
            <li role="presentation">
              <button
                type="button"
                role="tab"
                aria-selected={activeTab === 'old'}
                className={`inline-block p-2 rounded-t-lg border-b-2 ${activeTab === 'old' ? 'text-blue-600 border-blue-600 dark:text-blue-500 dark:border-blue-500 bg-gray-100 dark:bg-gray-800' : 'border-transparent hover:text-gray-600 dark:hover:text-gray-300'}`}
                onClick={() => setActiveTab('old')}>
                Check old proxy
              </button>
            </li>
            {executorFiles.map((f, i) => (
              <li role="presentation" key={f.name}>
                <button
                  type="button"
                  role="tab"
                  aria-selected={activeTab === 'executor' && executorIndex === i}
                  className={`inline-block p-2 rounded-t-lg border-b-2 ${activeTab === 'executor' && executorIndex === i ? 'text-blue-600 border-blue-600 dark:text-blue-500 dark:border-blue-500 bg-gray-100 dark:bg-gray-800' : 'border-transparent hover:text-gray-600 dark:hover:text-gray-300'}`}
                  onClick={() => {
                    setActiveTab('executor');
                    setExecutorIndex(i);
                  }}>
                  {f.name.replace(/(\.php|\.py)?\.logs?$/i, '')}
                </button>
              </li>
            ))}
          </ul>
          <div className="mt-2 flex items-center gap-2">
            <button
              type="button"
              title="Refresh executor list"
              aria-label="Refresh executor list"
              className="text-xs px-2 py-1 text-gray-600 dark:text-gray-300 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 rounded transition-colors"
              onClick={async () => {
                try {
                  const json = await get(createUrl('/php_backend/logs.php', { executor: 1 }));
                  if (Array.isArray(json)) setExecutorFiles(json);
                } catch (_err) {
                  // ignore
                }
              }}>
              <i className="fa-solid fa-sync" aria-hidden="true"></i>
            </button>

            <button
              type="button"
              title="Clear all executor logs"
              aria-label="Clear all executor logs"
              className="text-xs px-2 py-1 text-red-600 dark:text-red-300 bg-red-50 dark:bg-red-900 border border-red-100 dark:border-red-700 hover:bg-red-100 dark:hover:bg-red-800 rounded transition-colors"
              onClick={async () => {
                if (!confirm('Clear all executor logs for your user?')) return;
                try {
                  await get(createUrl('/php_backend/logs.php', { executor: 1, clear: 1 }));
                  // refresh list after clearing
                  try {
                    const json = await get(createUrl('/php_backend/logs.php', { executor: 1 }));
                    if (Array.isArray(json)) {
                      setExecutorFiles(json);
                    } else {
                      setExecutorFiles([]);
                    }
                  } catch (_err) {
                    setExecutorFiles([]);
                  }
                  // Reset UI to default state after clearing
                  setActiveTab('old');
                  setExecutorIndex(null);
                  setExecutorLog('');
                } catch (_err) {
                  // ignore
                }
              }}>
              <i className="fa-solid fa-trash" aria-hidden="true"></i>
            </button>
          </div>
        </div>

        {/* The old 'Checker Log' tab was removed because php_backend/proxy-checker.php no longer exists. */}

        {/* Default check tabs removed (HTTPS / HTTP / Type) */}

        {/* Check old proxy panel */}
        <div className={`${activeTab === 'old' ? '' : 'hidden'}`}>
          <div className="mb-2">
            <div className="flex flex-col sm:flex-row sm:items-center gap-1">
              <span className="text-xs text-gray-500 dark:text-gray-400">Logs check old proxy for UID:</span>
              <span className="font-mono text-sm text-gray-900 dark:text-gray-100 break-words max-w-full overflow-auto">
                {hash || '—'}
              </span>
            </div>
          </div>
          <div className="border border-gray-200 dark:border-gray-700 rounded-lg bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-gray-100 p-3 h-64 overflow-auto font-mono text-xs whitespace-pre-wrap transition-colors duration-300">
            {oldLog ? (
              <div
                dangerouslySetInnerHTML={{ __html: convertAnsiToHtml(oldLog) }}
                className="text-gray-800 dark:text-gray-100"
                style={{ whiteSpace: 'pre-wrap' }}
              />
            ) : (
              <span className="text-gray-400 dark:text-gray-500">No check old proxy result fetched yet.</span>
            )}
          </div>
        </div>
        {/* Executor dynamic panels */}
        <div className={`${activeTab === 'executor' ? '' : 'hidden'}`}>
          <div className="mb-2">
            <div className="flex flex-col sm:flex-row sm:items-center gap-1">
              <span className="text-xs text-gray-500 dark:text-gray-400">Executor log:</span>
              <span className="font-mono text-sm text-gray-900 dark:text-gray-100 break-words max-w-full overflow-auto">
                {executorIndex !== null && executorFiles[executorIndex]
                  ? executorFiles[executorIndex].name.replace(/(\.php|\.py)?\.logs?$/i, '')
                  : '—'}
              </span>
            </div>
          </div>
          <div className="border border-gray-200 dark:border-gray-700 rounded-lg bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-gray-100 p-3 h-64 overflow-auto font-mono text-xs whitespace-pre-wrap transition-colors duration-300">
            {executorLog ? (
              <div
                dangerouslySetInnerHTML={{ __html: convertAnsiToHtml(executorLog) }}
                className="text-gray-800 dark:text-gray-100"
                style={{ whiteSpace: 'pre-wrap' }}
              />
            ) : (
              <span className="text-gray-400 dark:text-gray-500">No executor log fetched yet.</span>
            )}
          </div>
        </div>
      </div>
    </section>
  );
};

export default LogViewer;
