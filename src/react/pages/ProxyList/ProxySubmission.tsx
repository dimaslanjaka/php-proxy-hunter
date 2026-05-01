import { ReactFormSaver, ReactFormSaverRef } from 'jquery-form-saver/react';
import React from 'react';
import { useTranslation } from 'react-i18next';
import { useSnackbar } from '../../components/Snackbar';
import { checkOldProxy, checkProxyHttps } from '../../utils/proxy';
import { createUrl } from '../../utils/url';
import { get, postForm, post } from '../../utils/ajax-helper';

export default function ProxySubmission() {
  const { t } = useTranslation();
  const [textarea, setTextarea] = React.useState('');

  const defaultProxyUrls = [
    'https://raw.githubusercontent.com/proxifly/free-proxy-list/refs/heads/main/proxies/protocols/socks5/data.txt',
    'https://raw.githubusercontent.com/proxifly/free-proxy-list/refs/heads/main/proxies/protocols/socks4/data.txt',

    'https://raw.githubusercontent.com/proxifly/free-proxy-list/refs/heads/main/proxies/protocols/http/data.txt'
  ];

  const [urlsInput, setUrlsInput] = React.useState(defaultProxyUrls.join('\n'));
  const [isLoading, setIsLoading] = React.useState(false);
  const [activeTab, setActiveTab] = React.useState<'submit' | 'fetch'>('submit');
  const formSaverRef = React.useRef<ReactFormSaverRef | null>(null);
  const lastFetchAt = React.useRef<number | null>(null);
  const { showSnackbar } = useSnackbar();
  const [selectedCheckBackend, setSelectedCheckBackend] = React.useState<string>('');
  const [executorList, setExecutorList] = React.useState<Array<{ name: string; path: string }>>([]);
  const STORAGE_KEY = 'proxy-submission.selectedCheckBackend';
  const STORAGE_KEY_LIMIT = 'proxy-submission.selectedCheckBackendLimit';
  const [limit, setLimit] = React.useState<string>('1');
  const nameCounts = React.useMemo(() => {
    const map: Record<string, number> = {};
    executorList.forEach((it) => {
      map[it.name] = (map[it.name] || 0) + 1;
    });
    return map;
  }, [executorList]);

  React.useEffect(() => {
    // fetch available executor scripts
    get(createUrl('/php_backend/executor.php', { list: 1 }))
      .then((json) => {
        if (Array.isArray(json)) {
          // Expect items like { name: string, path: string }
          const list = json.filter(
            (v) => v && typeof v === 'object' && typeof v.path === 'string' && typeof v.name === 'string'
          );
          setExecutorList(list as Array<{ name: string; path: string }>);
          // set default selected backend when list is available and none selected
          if (list.length > 0 && !selectedCheckBackend) {
            try {
              const saved = localStorage.getItem(STORAGE_KEY);
              if (saved && list.some((it) => it.path === saved)) {
                setSelectedCheckBackend(saved);
              } else {
                setSelectedCheckBackend(list[0].path);
              }
            } catch (_e) {
              setSelectedCheckBackend(list[0].path);
            }
          }
        }
      })
      .catch(() => {
        // ignore failures silently
      });
  }, []);

  // restore saved limit value
  React.useEffect(() => {
    try {
      const saved = localStorage.getItem(STORAGE_KEY_LIMIT);
      if (saved) setLimit(saved);
    } catch (_e) {
      // ignore
    }
  }, []);

  // persist limit selection
  React.useEffect(() => {
    try {
      localStorage.setItem(STORAGE_KEY_LIMIT, String(limit));
    } catch (_e) {
      // ignore
    }
  }, [limit]);

  // Persist selected executor so it remains selected when user returns
  React.useEffect(() => {
    try {
      if (selectedCheckBackend) {
        localStorage.setItem(STORAGE_KEY, selectedCheckBackend);
      }
    } catch (_e) {
      // ignore storage errors
    }
  }, [selectedCheckBackend]);

  const onRestore = (element: HTMLElement, data: any) => {
    if (element.id == 'proxyTextarea') {
      // If we recently fetched proxies, prefer the fetched content and skip
      // restoring the older saved value for a short grace period.
      const now = Date.now();
      if (lastFetchAt.current && now - lastFetchAt.current < 10000) {
        return;
      }
      setTextarea(data);
    }
  };

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setIsLoading(true);

    // Send proxies to backend for addition
    postForm(createUrl('/php_backend/proxy-add.php'), new URLSearchParams({ proxies: textarea }))
      .catch((_err) => {
        console.error(_err);
        try {
          showSnackbar({ message: `Submit failed: ${String(_err)}`, type: 'danger' });
        } catch {
          /* ignore if snackbar not available */
        }
      })
      .finally(() => {
        // Always use executor.php: pass `file` (endpoint or /artisan script) and `str` (proxies)
        setIsLoading(true);
        postForm(
          createUrl('/php_backend/executor.php'),
          new URLSearchParams({ file: selectedCheckBackend, str: textarea, limit: String(limit || '1') })
        )
          .then((json) => {
            const msg = json?.message || json?.logFile || JSON.stringify(json);
            try {
              showSnackbar({ message: msg || 'Executor started', type: json?.error ? 'danger' : 'success' });
            } catch {
              /* ignore */
            }
          })
          .catch((err) => {
            console.error(err);
            try {
              showSnackbar({ message: `Executor failed: ${String(err)}`, type: 'danger' });
            } catch {
              /* ignore */
            }
          })
          .finally(() => setIsLoading(false));
      });
  }

  function handleCheckOldProxy() {
    setIsLoading(true);
    checkOldProxy()
      .then((res) => {
        const isError = res?.error === true;
        try {
          showSnackbar({
            message: res?.message || (isError ? 'Check old proxy failed' : 'Old proxy check initiated'),
            type: isError ? 'danger' : 'success'
          });
        } catch {
          /* ignore */
        }
      })
      .catch((_err) => {
        console.error(_err);
        try {
          showSnackbar({ message: `Check old proxy failed: ${String(_err)}`, type: 'danger' });
        } catch {
          /* ignore */
        }
      })
      .finally(() => {
        setIsLoading(false);
      });
  }

  function handleCheckUntestedHttps() {
    // Fetch untested proxies from backend, then run HTTPS check on them
    // Request a capped number of untested proxies instead of asking for "all" (-1)
    const url = createUrl('/php_backend/proxy-list.php', { status: 'untested', length: 1000 });
    setIsLoading(true);
    post(url)
      .then((r) => r)
      .then((json) => {
        if (!json || !json.data || !Array.isArray(json.data) || json.data.length === 0) {
          try {
            showSnackbar({ message: 'No untested proxies found', type: 'danger' });
          } catch {
            /* ignore */
          }
          return;
        }
        const proxies = json.data
          .map((row: any) => row.proxy)
          .filter(Boolean)
          .join('\n');
        if (!proxies) {
          try {
            showSnackbar({ message: 'No proxy entries available to check', type: 'danger' });
          } catch {
            /* ignore */
          }
          return;
        }

        // Reuse runSingleCheck: wrap checkProxyHttps to ignore textarea and use fetched proxies
        runSingleCheck(() => checkProxyHttps(proxies), 'Untested HTTPS check initiated', 'Check untested HTTPS failed');
      })
      .catch((err) => {
        console.error(err);
        try {
          showSnackbar({ message: `Failed to fetch untested proxies: ${String(err)}`, type: 'danger' });
        } catch {
          /* ignore */
        }
      })
      .finally(() => {
        setIsLoading(false);
      });
  }

  function runSingleCheck(fn: (proxies: string) => Promise<any>, successLabel: string, failLabel: string) {
    setIsLoading(true);
    fn(textarea)
      .then((res) => {
        const isError = res?.error === true;
        try {
          showSnackbar({
            message: res?.message || (isError ? failLabel : successLabel),
            type: isError ? 'danger' : 'success'
          });
        } catch {
          /* ignore */
        }
      })
      .catch((_err) => {
        console.error(_err);
        try {
          showSnackbar({ message: `${failLabel}: ${String(_err)}`, type: 'danger' });
        } catch {
          /* ignore */
        }
      })
      .finally(() => {
        setIsLoading(false);
      });
  }

  function handleFetchProxiesFromUrl() {
    // Use custom URLs if provided, otherwise use defaults
    let urlsToFetch = urlsInput
      .trim()
      .split(/[\n,]+/)
      .map((u) => u.trim())
      .filter(Boolean);

    // Fall back to defaults if no custom URLs
    if (urlsToFetch.length === 0) {
      urlsToFetch = defaultProxyUrls;
    }

    setIsLoading(true);
    Promise.all(
      urlsToFetch.map((url) =>
        // Do not send credentials when fetching third-party proxy lists —
        // the proxy endpoint returns Access-Control-Allow-Origin: * which
        // is incompatible with credentialed requests. Override axios
        // `withCredentials` to false for these requests.
        get(createUrl('/php_backend/proxy.php', { url }), { responseType: 'text', withCredentials: false })
          .then((text) => (text == null ? '' : String(text)))
          .catch((err) => {
            console.error(`Failed to fetch from ${url}:`, err);
            return '';
          })
      )
    )
      .then((results) => {
        const allProxies = results
          .map((text) => text.trim())
          .filter(Boolean)
          .join('\n');

        if (!allProxies) {
          try {
            showSnackbar({ message: 'No proxies retrieved from URLs', type: 'danger' });
          } catch {
            /* ignore */
          }
          return;
        }

        // Replace textarea with fetched proxies (not append)
        // Always update React state so the value appears when switching to the Submit tab,
        // even if the textarea DOM element is not currently mounted.
        setTextarea(allProxies);
        // Record fetch time so onRestore won't overwrite immediately
        if (typeof lastFetchAt !== 'undefined' && lastFetchAt !== null) {
          lastFetchAt.current = Date.now();
        }
        // Ensure the Submit tab is active and the textarea DOM is updated. Use
        // requestAnimationFrame/timeout to allow React to mount the textarea before syncing.
        setActiveTab('submit');
        requestAnimationFrame(() => {
          const textareaElement = document.getElementById('proxyTextarea') as HTMLTextAreaElement | null;
          if (textareaElement) {
            textareaElement.value = allProxies;
            // Sync with form saver immediately
            if (formSaverRef.current) {
              try {
                formSaverRef.current.saveElementValue(textareaElement);
              } catch (_e) {
                // ignore errors from form saver
              }
            }
          }
        });

        try {
          showSnackbar({
            message: `Retrieved ${allProxies.split('\n').length} proxies from ${urlsToFetch.length} URL(s)`,
            type: 'success'
          });
        } catch {
          /* ignore */
        }
      })
      .catch((_err) => {
        console.error(_err);
        try {
          showSnackbar({ message: `Failed to fetch proxies: ${String(_err)}`, type: 'danger' });
        } catch {
          /* ignore */
        }
      })
      .finally(() => {
        setIsLoading(false);
      });
  }

  return (
    <section className="my-8">
      <div
        className="relative bg-white dark:bg-gray-900 rounded-xl shadow-lg dark:shadow-white border border-blue-200 dark:border-blue-700 transition-colors duration-300 flowbite-modal"
        aria-busy={isLoading}>
        {/* Tab Header */}
        <div className="flex border-b border-gray-200 dark:border-gray-700">
          <button
            onClick={() => setActiveTab('submit')}
            className={`flex-1 px-4 py-3 text-sm font-medium transition-colors ${
              activeTab === 'submit'
                ? 'border-b-2 border-blue-600 text-blue-600 dark:text-blue-400'
                : 'text-gray-600 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'
            }`}>
            <i className="fa-duotone fa-magnifying-glass mr-2"></i>
            Submit & Check
          </button>
          <button
            onClick={() => setActiveTab('fetch')}
            className={`flex-1 px-4 py-3 text-sm font-medium transition-colors ${
              activeTab === 'fetch'
                ? 'border-b-2 border-blue-600 text-blue-600 dark:text-blue-400'
                : 'text-gray-600 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'
            }`}>
            <i className="fa-duotone fa-cloud-arrow-down mr-2"></i>
            Fetch Proxies
          </button>
        </div>

        {/* Tab Content */}
        <div className="p-6">
          {/* Submit & Check Tab */}
          {activeTab === 'submit' && (
            <div>
              <div className="flex justify-between items-center mb-4">
                <h2 className="text-lg font-bold text-blue-800 dark:text-blue-200 flex items-center gap-2">
                  <i
                    className="fa-duotone fa-magnifying-glass mr-1 text-gray-700 dark:text-gray-200"
                    aria-hidden="true"></i>
                  Proxy Submission
                </h2>
                <div className="flex items-center gap-2">
                  <button
                    type="button"
                    className="inline-flex items-center justify-center gap-2 px-2 md:px-3 py-1 text-xs font-medium text-blue-600 dark:text-blue-200 bg-blue-50 dark:bg-blue-900 border border-blue-200 dark:border-blue-700 rounded-lg hover:bg-blue-100 dark:hover:bg-blue-800 transition-colors flex-shrink-0 w-10 md:w-auto h-8"
                    title={t('populate_with_sample_proxies')}
                    onClick={() => {
                      const sampleProxies = `103.160.204.144:80\n103.160.204.144:80@username:password\nuser:pass@103.160.204.144:80`;
                      const textareaElement = document.getElementById('proxyTextarea') as HTMLTextAreaElement;
                      if (textareaElement) {
                        textareaElement.value = sampleProxies;
                        setTextarea(sampleProxies);
                        if (formSaverRef.current) {
                          formSaverRef.current.saveElementValue(textareaElement);
                        }
                      }
                    }}>
                    <i className="fa-duotone fa-wand-magic-sparkles"></i>
                    <span className="hidden md:inline">{t('populate_with_sample_proxies')}</span>
                  </button>
                </div>
              </div>
              {/* Executor selector and limit - placed below header to keep header inline with Populate */}
              <div className="mb-3">
                <div className="flex gap-2 items-center flex-wrap">
                  <div className="min-w-0 flex-1">
                    <label htmlFor="checkBackend" className="sr-only">
                      Select check backend
                    </label>
                    <select
                      id="checkBackend"
                      value={selectedCheckBackend}
                      onChange={(e) => setSelectedCheckBackend(e.target.value)}
                      className="text-xs p-2 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 w-full truncate"
                      aria-label="Select executor backend">
                      {executorList.length > 0 ? (
                        executorList.map((it) => {
                          const showFilename = nameCounts[it.name] > 1;
                          const ext = it.path.endsWith('.py') ? '.py' : it.path.endsWith('.php') ? '.php' : '';
                          const label = showFilename ? `${it.name} (${ext})` : it.name;
                          return (
                            <option key={it.path} value={it.path}>
                              {label}
                            </option>
                          );
                        })
                      ) : (
                        <option value="" disabled>
                          Loading executor list...
                        </option>
                      )}
                    </select>
                  </div>
                  <div className="flex-shrink-0">
                    <label htmlFor="executorLimit" className="sr-only">
                      Limit
                    </label>
                    <input
                      id="executorLimit"
                      type="number"
                      min={1}
                      value={limit}
                      onChange={(e) => setLimit(String(Math.max(1, Number(e.target.value || '1'))))}
                      className="text-xs p-2 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 w-20"
                      title="Limit"
                      aria-label="Executor limit"
                    />
                  </div>
                </div>
              </div>
              <ReactFormSaver
                ref={formSaverRef}
                onRestore={onRestore}
                storagePrefix="proxy-submission"
                className="mb-4"
                onSubmit={handleSubmit}>
                <div className="mb-2 grid grid-cols-1 sm:grid-cols-3 gap-2">
                  <button
                    type="button"
                    disabled={isLoading}
                    className="col-span-1 w-full inline-flex items-center justify-center gap-1 px-4 py-2 text-sm font-medium text-white bg-rose-600 hover:bg-rose-700 disabled:bg-gray-400 active:bg-rose-800 rounded-lg transition-colors"
                    onClick={handleCheckUntestedHttps}>
                    <i className="fa-duotone fa-circle-question"></i>
                    <span className="ml-2">Check Untested</span>
                  </button>
                  <button
                    type="submit"
                    disabled={isLoading || executorList.length === 0 || !selectedCheckBackend}
                    className="col-span-1 w-full inline-flex items-center justify-center gap-1 px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 active:bg-blue-800 rounded-lg transition-colors">
                    <i className="fa-duotone fa-paper-plane"></i>
                    <span className="ml-2">
                      {(() => {
                        if (selectedCheckBackend.startsWith('/php_backend/')) {
                          return selectedCheckBackend.replace('/php_backend/', '');
                        }
                        if (selectedCheckBackend.startsWith('/artisan/')) {
                          const found = executorList.find((it) => it.path === selectedCheckBackend);
                          if (found) {
                            const ext = found.path.endsWith('.py') ? '.py' : found.path.endsWith('.php') ? '.php' : '';
                            return nameCounts[found.name] > 1 ? `${found.name} (${ext})` : found.name;
                          }
                          return selectedCheckBackend.replace('/artisan/', '');
                        }
                        return 'Run';
                      })()}
                    </span>
                  </button>
                  <button
                    type="button"
                    disabled={isLoading}
                    className="col-span-1 w-full inline-flex items-center justify-center gap-1 px-4 py-2 text-sm font-medium text-white bg-orange-600 hover:bg-orange-700 disabled:bg-gray-400 active:bg-orange-800 rounded-lg transition-colors"
                    onClick={handleCheckOldProxy}>
                    <i className="fa-duotone fa-clock"></i>
                    <span className="ml-2">Check Old Proxies</span>
                  </button>
                </div>
                <div className="mb-1">
                  <label
                    htmlFor="proxyTextarea"
                    className="block text-sm font-medium text-gray-700 dark:text-gray-200 mr-2">
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
              </ReactFormSaver>
            </div>
          )}

          {/* Fetch Proxies Tab */}
          {activeTab === 'fetch' && (
            <div>
              <h2 className="text-lg font-bold text-blue-800 dark:text-blue-200 mb-4 flex items-center gap-2">
                <i className="fa-duotone fa-cloud-arrow-down text-gray-700 dark:text-gray-200"></i>
                Fetch Proxy Lists
              </h2>
              <div className="mb-4 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                <div className="flex justify-between items-center mb-3">
                  <label htmlFor="urlsInput" className="block text-sm font-medium text-gray-700 dark:text-gray-200">
                    Proxy List URLs
                  </label>
                  <button
                    type="button"
                    disabled={isLoading}
                    className="text-xs px-2 py-1 text-gray-600 dark:text-gray-300 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 rounded transition-colors"
                    onClick={() => setUrlsInput(defaultProxyUrls.join('\n'))}>
                    Load Defaults
                  </button>
                </div>
                <textarea
                  id="urlsInput"
                  rows={3}
                  className="w-full p-2 border border-gray-300 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-700 text-xs text-gray-900 dark:text-gray-100 mb-2"
                  placeholder="Enter proxy list URLs (one per line or comma-separated)"
                  value={urlsInput}
                  onChange={(e) => setUrlsInput(e.target.value)}
                />
                <p className="text-xs text-gray-500 dark:text-gray-400">
                  Use newline or comma to separate multiple URLs
                </p>
              </div>
              <button
                type="button"
                disabled={isLoading}
                className="inline-flex items-center gap-1 px-4 py-2 text-sm font-medium text-white bg-teal-600 hover:bg-teal-700 disabled:bg-gray-400 active:bg-teal-800 rounded-lg transition-colors w-full justify-center"
                onClick={handleFetchProxiesFromUrl}
                title={urlsInput ? 'Fetch from custom URLs' : 'Fetch from default sources'}>
                <i className="fa-duotone fa-cloud-arrow-down"></i>
                Fetch Proxies
              </button>
            </div>
          )}
        </div>

        {isLoading && (
          <div className="absolute inset-0 z-30 flex items-center justify-center bg-white/60 dark:bg-black/60 backdrop-blur-sm pointer-events-auto rounded-xl">
            <div className="px-4 py-3 rounded-lg shadow bg-white dark:bg-gray-900/80 flex items-center gap-3">
              <i
                className="fa-duotone fa-spinner fa-spin text-2xl text-gray-700 dark:text-gray-200"
                aria-hidden="true"
              />
              <span className="text-sm text-gray-700 dark:text-gray-200">Checking...</span>
            </div>
          </div>
        )}
      </div>
    </section>
  );
}
