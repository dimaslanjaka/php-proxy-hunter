import React from 'react';
import { createUrl } from '../utils/url';
import { get } from '../utils/ajax-helper';
import { convertAnsiToHtml } from '../utils/ansi-to-html';

export default function ProcessMonitor() {
  const [processOutput, setProcessOutput] = React.useState<string>('');
  const [loading, setLoading] = React.useState<boolean>(false);
  const [error, setError] = React.useState<string>('');

  const fetchProcesses = React.useCallback(async () => {
    setLoading(true);

    try {
      setError('');
      const text = await get<string>(createUrl('/php_backend/processes.php', { color: 1 }), {
        responseType: 'text'
      } as any);

      setProcessOutput(typeof text === 'string' ? text : '');
    } catch (fetchError) {
      const message = fetchError instanceof Error ? fetchError.message : 'Unable to fetch process list.';
      setError(message);
      setProcessOutput('');
    } finally {
      setLoading(false);
    }
  }, []);

  React.useEffect(() => {
    void fetchProcesses();
  }, [fetchProcesses]);

  return (
    <section className="my-8">
      <div className="bg-white dark:bg-gray-900 rounded-xl shadow-lg dark:shadow-white border border-slate-200 dark:border-slate-700 p-6 transition-colors duration-300">
        <div className="flex items-center justify-between gap-3 mb-4">
          <h2 className="text-lg font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
            <i className="fa-duotone fa-wave-square text-emerald-600 dark:text-emerald-400" aria-hidden="true"></i>
            PHP / Python Processes
          </h2>

          <button
            type="button"
            onClick={() => fetchProcesses()}
            disabled={loading}
            aria-label="Refresh processes"
            className="inline-flex items-center justify-center gap-2 text-xs px-3 py-1.5 rounded-md border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 disabled:opacity-70 transition-colors">
            <i className="fa-solid fa-rotate-right" aria-hidden="true" />
            <span className="hidden sm:inline">{loading ? 'Refreshing...' : 'Refresh'}</span>
          </button>
        </div>

        <p className="text-sm text-slate-600 dark:text-slate-300 mb-3">
          Live process monitor showing PHP and Python workers for the current user.
        </p>

        {error ? (
          <div className="mb-4 rounded border border-red-300 bg-red-50 text-red-700 px-3 py-2 text-sm">{error}</div>
        ) : null}

        <div className="border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-950 text-slate-100 p-3 h-72 overflow-auto font-mono text-xs whitespace-pre-wrap transition-colors duration-300">
          {loading ? (
            <span className="text-slate-400 dark:text-slate-500">Loading process list...</span>
          ) : processOutput ? (
            <div
              dangerouslySetInnerHTML={{ __html: convertAnsiToHtml(processOutput) }}
              style={{ whiteSpace: 'pre-wrap' }}
            />
          ) : (
            <span className="text-slate-400 dark:text-slate-500">No process information available.</span>
          )}
        </div>
      </div>
    </section>
  );
}
