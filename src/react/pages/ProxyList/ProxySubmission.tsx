import { ReactFormSaver, ReactFormSaverRef } from 'jquery-form-saver/react';
import React from 'react';
import { useTranslation } from 'react-i18next';
import { checkProxy, checkOldProxy, checkProxyType, checkProxyHttp, checkProxyHttps } from '../../utils/proxy';
import { useSnackbar } from '../../components/Snackbar';
import { createUrl } from '../../utils/url';

export default function ProxySubmission() {
  const { t } = useTranslation();
  const [textarea, setTextarea] = React.useState('');
  const [isLoading, setIsLoading] = React.useState(false);
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

  return (
    <section className="my-8">
      <div
        className="relative bg-white dark:bg-gray-900 rounded-xl shadow-lg dark:shadow-white border border-blue-200 dark:border-blue-700 p-6 transition-colors duration-300 flowbite-modal"
        aria-busy={isLoading}>
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
        <ReactFormSaver
          ref={formSaverRef}
          onRestore={onRestore}
          storagePrefix="proxy-submission"
          className="mb-4"
          onSubmit={handleSubmit}>
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
          <div className="flex gap-2 flex-wrap">
            <button
              type="button"
              disabled={isLoading || !textarea}
              className="inline-flex items-center gap-1 px-4 py-2 text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700 disabled:bg-gray-400 active:bg-emerald-800 rounded-lg transition-colors"
              onClick={() => runSingleCheck(checkProxyType, 'Type check initiated', 'Check type failed')}>
              <i className="fa-duotone fa-filter"></i>
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
        {isLoading && (
          <div className="absolute inset-0 z-30 flex items-center justify-center bg-white/60 dark:bg-black/60 backdrop-blur-sm pointer-events-auto">
            <div className="px-4 py-3 rounded-lg shadow bg-white dark:bg-gray-900/80 flex items-center gap-3">
              <i
                className="fa-duotone fa-spinner fa-spin text-2xl text-gray-700 dark:text-gray-200"
                aria-hidden="true"
              />
              <span className="text-sm text-gray-700 dark:text-gray-200">Checking...</span>
            </div>
          </div>
        )}
        {/* Log and status URLs removed */}
        {/* Add more UI elements as needed */}
      </div>
    </section>
  );
}
