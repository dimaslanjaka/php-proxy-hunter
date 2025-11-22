import { ReactFormSaver, ReactFormSaverRef } from 'jquery-form-saver/react';
import React from 'react';
import { useTranslation } from 'react-i18next';
import { extractProxies } from '../../../proxy/extractor';
import ProxyData from '../../../proxy/ProxyData';
import { checkProxy } from '../../utils/proxy';
import { createUrl } from '../../utils/url';

export default function ProxySubmission() {
  const { t } = useTranslation();
  const [textarea, setTextarea] = React.useState('');
  const [proxyDatas, setProxyDatas] = React.useState<ProxyData[]>([]);
  const formSaverRef = React.useRef<ReactFormSaverRef | null>(null);

  React.useEffect(() => {
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
      .then((res) => res.text())
      .then((data) => {
        console.log(data);
      })
      .catch((err) => {
        console.error(err);
      })
      .finally(() => {
        checkProxy(textarea);
      });

    // Split textarea into lines and extract proxies from each line
    // extractProxies returns ProxyData objects; normalize and type assert safely
    const parsedAny = textarea.split(/\r?\n/).map(extractProxies).flat().filter(Boolean) as any[];
    const parsed = parsedAny.map((pd) => {
      if (typeof pd === 'string') return new ProxyData({ proxy: pd });
      return pd;
    }) as ProxyData[];
    setProxyDatas(parsed);
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
      <div className="bg-white dark:bg-gray-900 rounded-xl shadow-lg dark:shadow-white border border-blue-200 dark:border-blue-700 p-6 transition-colors duration-300 flowbite-modal">
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
        {/* Log and status URLs removed */}
        {/* Add more UI elements as needed */}
      </div>
    </section>
  );
}
