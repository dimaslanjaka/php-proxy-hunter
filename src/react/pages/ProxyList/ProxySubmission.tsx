import { ReactFormSaver, ReactFormSaverRef } from 'jquery-form-saver/react';
import React from 'react';
import { useTranslation } from 'react-i18next';
import { checkProxy, checkOldProxy, checkProxyType, checkProxyHttp, checkProxyHttps } from '../../utils/proxy';
import { useSnackbar } from '../../components/Snackbar';
import { createUrl } from '../../utils/url';

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
  const { showSnackbar } = useSnackbar();

  const onRestore = (element: HTMLElement, data: any) => {
    if (element.id == 'proxyTextarea') {
      setTextarea(data);
    }
  };

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setIsLoading(true);

    // Send proxies to backend for addition
    fetch(createUrl('/php_backend/proxy-add.php'), { method: 'POST', body: new URLSearchParams({ proxies: textarea }) })
      .catch((_err) => {
        console.error(_err);
        try {
          showSnackbar({ message: `Submit failed: ${String(_err)}`, type: 'danger' });
        } catch {
          /* ignore if snackbar not available */
        }
      })
      .finally(() => {
        checkProxy(textarea)
          .then((res) => {
            // res: { error?: boolean; message: string }
            const isError = res?.error === true;
            try {
              showSnackbar({
                message: res?.message || (isError ? 'Proxy check failed' : 'Proxies checked'),
                type: isError ? 'danger' : 'success'
              });
            } catch {
              /* ignore */
            }
          })
          .catch((_err) => {
            console.error(_err);
            try {
              showSnackbar({ message: `Proxy check failed: ${String(_err)}`, type: 'danger' });
            } catch {
              /* ignore */
            }
          })
          .finally(() => {
            setIsLoading(false);
          });
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
    fetch(url, { method: 'POST' })
      .then((r) => r.json())
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
        fetch(createUrl('/php_backend/proxy.php', { url }))
          .then((r) => {
            if (!r.ok) {
              console.error(`Failed to fetch from ${url}: HTTP ${r.status}`);
              return '';
            }
            return r.text();
          })
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
        const textareaElement = document.getElementById('proxyTextarea') as HTMLTextAreaElement;
        if (textareaElement) {
          textareaElement.value = allProxies;
          setTextarea(allProxies);

          // Sync with form saver immediately
          if (formSaverRef.current) {
            formSaverRef.current.saveElementValue(textareaElement);
          }
        }

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
                <div className="flex gap-2">
                  <button
                    type="button"
                    className="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-blue-600 dark:text-blue-200 bg-blue-50 dark:bg-blue-900 border border-blue-200 dark:border-blue-700 rounded-lg hover:bg-blue-100 dark:hover:bg-blue-800 transition-colors"
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
                    <i className="fa-duotone fa-wand-magic-sparkles"></i> Populate
                  </button>
                </div>
              </div>
              <ReactFormSaver
                ref={formSaverRef}
                onRestore={onRestore}
                storagePrefix="proxy-submission"
                className="mb-4"
                onSubmit={handleSubmit}>
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
                <div className="flex gap-2 flex-wrap">
                  <button
                    type="button"
                    disabled={isLoading || !textarea}
                    className="inline-flex items-center gap-1 px-4 py-2 text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700 disabled:bg-gray-400 active:bg-emerald-800 rounded-lg transition-colors"
                    onClick={() => runSingleCheck(checkProxyType, 'Type check initiated', 'Check type failed')}>
                    <i className="fa-duotone fa-solid fa-filter" aria-hidden="true"></i>
                    Check Type
                  </button>
                  <button
                    type="button"
                    disabled={isLoading || !textarea}
                    className="inline-flex items-center gap-1 px-4 py-2 text-sm font-medium text-white bg-sky-600 hover:bg-sky-700 disabled:bg-gray-400 active:bg-sky-800 rounded-lg transition-colors"
                    onClick={() => runSingleCheck(checkProxyHttp, 'HTTP check initiated', 'Check HTTP failed')}>
                    <i className="fa-duotone fa-globe"></i>
                    Check HTTP
                  </button>
                  <button
                    type="button"
                    disabled={isLoading || !textarea}
                    className="inline-flex items-center gap-1 px-4 py-2 text-sm font-medium text-white bg-violet-600 hover:bg-violet-700 disabled:bg-gray-400 active:bg-violet-800 rounded-lg transition-colors"
                    onClick={() => runSingleCheck(checkProxyHttps, 'HTTPS check initiated', 'Check HTTPS failed')}>
                    <i className="fa-duotone fa-lock"></i>
                    Check HTTPS
                  </button>
                  <button
                    type="button"
                    disabled={isLoading}
                    className="inline-flex items-center gap-1 px-4 py-2 text-sm font-medium text-white bg-rose-600 hover:bg-rose-700 disabled:bg-gray-400 active:bg-rose-800 rounded-lg transition-colors"
                    onClick={handleCheckUntestedHttps}>
                    <i className="fa-duotone fa-circle-question"></i>
                    Check Untested
                  </button>
                  <button
                    type="submit"
                    disabled={isLoading}
                    className="inline-flex items-center gap-1 px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 active:bg-blue-800 rounded-lg transition-colors">
                    <i className="fa-duotone fa-paper-plane"></i>
                    Check All
                  </button>
                  <button
                    type="button"
                    disabled={isLoading}
                    className="inline-flex items-center gap-1 px-4 py-2 text-sm font-medium text-white bg-orange-600 hover:bg-orange-700 disabled:bg-gray-400 active:bg-orange-800 rounded-lg transition-colors"
                    onClick={handleCheckOldProxy}>
                    <i className="fa-duotone fa-clock"></i>
                    Check Old Proxies
                  </button>
                </div>
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
