import React from 'react';
import { useTranslation } from 'react-i18next';
import { ReactFormSaver, ReactFormSaverRef } from 'jquery-form-saver/react';
import { useSnackbar } from '../../components/Snackbar';
import * as extractor from '../../../proxy/extractor';
import { isEmpty } from '../../../utils/string';

export default function ProxyExtractor() {
  const { t } = useTranslation();
  const { showSnackbar } = useSnackbar();
  const [textarea, setTextarea] = React.useState('');
  const [isLoading, setIsLoading] = React.useState(false);
  const [results, setResults] = React.useState<any[]>([]);
  const formSaverRef = React.useRef<ReactFormSaverRef | null>(null);

  const onRestore = (element: HTMLElement, data: any) => {
    if (element.id === 'extractorTextarea') setTextarea(data);
  };

  const formattedResults = React.useMemo(() => {
    if (!results || results.length === 0) return '';
    const mapped = results.map((r) => {
      try {
        if (r && typeof r === 'object') {
          const addr = (r.address || r.proxy || '').toString().trim();
          const user = (r.username || (r.auth ? r.auth.split(':')[0] : undefined) || '').toString().trim();
          const pass = (r.password || (r.auth ? r.auth.split(':')[1] : undefined) || '').toString().trim();
          if (addr && user && pass) return `${addr}@${user}:${pass}`;
          if (addr) return addr;
        }
        if (typeof r === 'string') return r.trim();
        return JSON.stringify(r);
      } catch {
        return JSON.stringify(r);
      }
    });

    // Deduplicate while preserving order
    const seen = new Set();
    const unique = [] as string[];
    for (const line of mapped) {
      const l = (line || '').toString().trim();
      if (!l) continue;
      if (seen.has(l)) continue;
      seen.add(l);
      unique.push(l);
    }
    return unique.join('\n');
  }, [results]);

  const resultCount = React.useMemo(() => {
    return formattedResults ? formattedResults.split('\n').filter(Boolean).length : 0;
  }, [formattedResults]);

  function handlePopulate() {
    setTextarea(`103.160.204.144:80\n103.160.204.144:80@username:password\nuser:pass@103.160.204.144:80`);
  }

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setIsLoading(true);
    try {
      const objs = (extractor as any).extractProxiesToObject(textarea || '');
      setResults(objs || []);
      showSnackbar?.({ message: `Extracted ${objs?.length || 0} proxies`, type: 'success' });
    } catch (err) {
      console.error(err);
      showSnackbar?.({ message: `Extraction failed: ${String(err)}`, type: 'danger' });
      setResults([]);
    } finally {
      setIsLoading(false);
    }
  }

  return (
    <section className="my-8">
      <div
        className="relative bg-white dark:bg-gray-900 rounded-xl shadow-lg dark:shadow-white border border-blue-200 dark:border-blue-700 p-6 transition-colors duration-300 flowbite-modal"
        aria-busy={isLoading}>
        <div className="flex justify-between items-center mb-4">
          <h2 className="text-lg font-bold text-blue-800 dark:text-blue-200 flex items-center gap-2">
            <i className="fa-duotone fa-solid fa-filter mr-1 text-gray-700 dark:text-gray-200" aria-hidden="true"></i>
            Proxy Extractor
          </h2>
          <div className="flex gap-2">
            <button
              type="button"
              className="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-blue-600 dark:text-blue-200 bg-blue-50 dark:bg-blue-900 border border-blue-200 dark:border-blue-700 rounded-lg hover:bg-blue-100 dark:hover:bg-blue-800 transition-colors"
              title={t('populate_with_sample_proxies')}
              onClick={handlePopulate}>
              <i className="fa-duotone fa-wand-magic-sparkles"></i> Populate
            </button>
          </div>
        </div>

        <ReactFormSaver
          ref={formSaverRef}
          onRestore={onRestore}
          storagePrefix="proxy-extractor"
          className="mb-4"
          onSubmit={handleSubmit}>
          <div className="mb-1">
            <label
              htmlFor="extractorTextarea"
              className="block text-sm font-medium text-gray-700 dark:text-gray-200 mr-2">
              Input
            </label>
          </div>
          <textarea
            id="extractorTextarea"
            name="extractor"
            rows={4}
            className="w-full p-2 border border-gray-300 dark:border-gray-700 rounded-lg bg-gray-50 dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-100 mb-2"
            placeholder={`103.160.204.144:80\n103.160.204.144:80@username:password\nuser:pass@103.160.204.144:80`}
            value={textarea}
            onChange={(e) => setTextarea(e.target.value)}
          />

          <div className="flex gap-2 flex-wrap">
            <button
              type="submit"
              disabled={isLoading || !textarea}
              className="inline-flex items-center gap-1 px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 active:bg-blue-800 rounded-lg transition-colors">
              <i className="fa-duotone fa-magnifying-glass"></i>
              Extract
            </button>
          </div>
        </ReactFormSaver>

        <div>
          <div className="mb-1">
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-200 mr-2">
              Results
              {resultCount > 0 && (
                <span className="ml-2 text-sm font-normal text-gray-600 dark:text-gray-400">
                  ({resultCount} extracted)
                </span>
              )}
            </label>
          </div>
          <textarea
            readOnly
            rows={8}
            className="w-full p-2 border border-gray-300 dark:border-gray-700 rounded-lg bg-gray-50 dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-100 mb-2"
            value={!isEmpty(formattedResults) ? formattedResults : ''}
          />
        </div>

        {isLoading && (
          <div className="absolute inset-0 z-30 flex items-center justify-center bg-white/60 dark:bg-black/60 backdrop-blur-sm pointer-events-auto">
            <div className="px-4 py-3 rounded-lg shadow bg-white dark:bg-gray-900/80 flex items-center gap-3">
              <i
                className="fa-duotone fa-spinner fa-spin text-2xl text-gray-700 dark:text-gray-200"
                aria-hidden="true"
              />
              <span className="text-sm text-gray-700 dark:text-gray-200">Extracting...</span>
            </div>
          </div>
        )}
      </div>
    </section>
  );
}
