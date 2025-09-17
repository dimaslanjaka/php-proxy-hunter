import React from 'react';
import { createUrl } from '../../utils/url';

export type LogViewerState = {
  open: boolean;
  url: string;
  statusUrl?: string;
  loading: boolean;
  log: string;
  intervalId?: number;
};

interface LogViewerProps {
  logViewer: LogViewerState;
}

const LogViewer: React.FC<LogViewerProps> = ({ logViewer }) => {
  const [loading, setLoading] = React.useState(false);
  const [log, setLog] = React.useState(logViewer.log);
  const [url, setUrl] = React.useState(logViewer.url);

  React.useEffect(() => {
    setLog(logViewer.log);
  }, [logViewer.log]);

  React.useEffect(() => {
    setUrl(logViewer.url);
  }, [logViewer.url]);

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
            <button
              type="button"
              className="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-gray-400 dark:text-gray-600 bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg cursor-not-allowed opacity-50"
              disabled
              tabIndex={-1}
              aria-label="Log viewer always visible">
              <i className="fa-duotone fa-xmark text-base"></i> Close
            </button>
          </div>
        </div>
        <div className="mb-2 text-xs text-gray-600 dark:text-gray-300 break-all">
          <span className="font-mono">{url}</span>
        </div>
        <div className="border border-gray-200 dark:border-gray-700 rounded-lg bg-gray-50 dark:bg-gray-800 p-3 h-64 overflow-auto font-mono text-xs whitespace-pre-wrap transition-colors duration-300">
          {loading ? (
            <span className="text-gray-400 dark:text-gray-500">Loading log...</span>
          ) : log ? (
            <span className="text-gray-800 dark:text-gray-100">{log}</span>
          ) : (
            <span className="text-gray-400 dark:text-gray-500">No log output yet.</span>
          )}
        </div>
      </div>
    </section>
  );
};

export default LogViewer;
