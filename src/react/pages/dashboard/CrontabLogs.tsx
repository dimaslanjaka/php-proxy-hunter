import React from 'react';
import { createUrl } from '../../utils/url';
import { convertAnsiToHtml } from '../../utils/ansi-to-html';

type CrontabLogItem = {
  name: string;
  path: string;
  size: string;
  mtime: number;
};

type CrontabLogsResponse = {
  logs?: CrontabLogItem[];
};

function formatTimestamp(ts: number): string {
  if (!ts) return '-';
  try {
    return new Date(ts * 1000).toLocaleString();
  } catch (_err) {
    return '-';
  }
}

export default function CrontabLogs() {
  const [logs, setLogs] = React.useState<CrontabLogItem[]>([]);
  const [loadingList, setLoadingList] = React.useState<boolean>(true);
  const [listError, setListError] = React.useState<string>('');

  const [selectedFile, setSelectedFile] = React.useState<string>('');
  const [logContent, setLogContent] = React.useState<string>('');
  const [loadingContent, setLoadingContent] = React.useState<boolean>(false);
  const [contentError, setContentError] = React.useState<string>('');

  const fetchCrontabLogs = React.useCallback(async () => {
    setLoadingList(true);
    setListError('');
    try {
      const response = await fetch(createUrl('/php_backend/logs.php', { cron: 1 }), {
        credentials: 'include'
      });
      if (!response.ok) {
        throw new Error(`Failed to load logs list (${response.status})`);
      }

      const data = (await response.json()) as CrontabLogsResponse;
      setLogs(Array.isArray(data.logs) ? data.logs : []);
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Unable to fetch crontab logs.';
      setListError(message);
      setLogs([]);
    } finally {
      setLoadingList(false);
    }
  }, []);

  const fetchFileContent = React.useCallback(async (fileName: string) => {
    setSelectedFile(fileName);
    setLoadingContent(true);
    setContentError('');
    setLogContent('');
    try {
      const response = await fetch(createUrl('/php_backend/logs.php', { cron: 1, file: fileName }), {
        credentials: 'include'
      });
      if (!response.ok) {
        throw new Error(`Failed to load ${fileName} (${response.status})`);
      }

      const text = await response.text();
      setLogContent(text || 'This log file is empty.');
    } catch (error) {
      const message = error instanceof Error ? error.message : `Unable to fetch ${fileName}.`;
      setContentError(message);
      setLogContent('');
    } finally {
      setLoadingContent(false);
    }
  }, []);

  React.useEffect(() => {
    fetchCrontabLogs();
  }, [fetchCrontabLogs]);

  return (
    <div className="w-full px-2 sm:px-4 md:px-6 lg:px-8 mt-4 transition-colors">
      <div className="w-full bg-white dark:bg-gray-800 rounded-lg shadow-lg p-4 sm:p-6">
        <div className="flex items-center justify-between gap-3 mb-4">
          <h2 className="text-xl font-bold text-blue-700 dark:text-blue-300 flex items-center gap-2">
            <i className="fa-duotone fa-clock-rotate-left text-green-500 dark:text-green-400" aria-hidden="true"></i>
            Crontab Logs
          </h2>
          <button
            type="button"
            onClick={() => fetchCrontabLogs()}
            disabled={loadingList}
            className="px-3 py-1.5 bg-blue-500 hover:bg-blue-600 disabled:opacity-70 text-white text-sm font-semibold rounded transition-colors">
            {loadingList ? 'Refreshing...' : 'Refresh'}
          </button>
        </div>

        {listError ? (
          <div className="mb-4 rounded border border-red-300 bg-red-50 text-red-700 px-3 py-2 text-sm">{listError}</div>
        ) : null}

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
          <div className="lg:col-span-1 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
            <div className="px-3 py-2 bg-gray-100 dark:bg-gray-700 text-sm font-semibold text-gray-700 dark:text-gray-100">
              Available Files
            </div>

            <div className="max-h-[460px] overflow-y-auto">
              {loadingList ? (
                <p className="p-3 text-sm text-gray-500 dark:text-gray-300">Loading crontab logs...</p>
              ) : logs.length === 0 ? (
                <p className="p-3 text-sm text-gray-500 dark:text-gray-300">No crontab logs found.</p>
              ) : (
                <ul className="divide-y divide-gray-200 dark:divide-gray-700">
                  {logs.map((item) => {
                    const isActive = selectedFile === item.name;
                    return (
                      <li key={item.name}>
                        <button
                          type="button"
                          onClick={() => fetchFileContent(item.name)}
                          className={`w-full text-left p-3 transition-colors ${
                            isActive ? 'bg-blue-50 dark:bg-blue-900/30' : 'hover:bg-gray-50 dark:hover:bg-gray-700/40'
                          }`}>
                          <p className="text-sm font-medium text-gray-900 dark:text-gray-100 break-words">
                            {item.name}
                          </p>
                          <p className="text-xs text-gray-500 dark:text-gray-300 mt-1">{item.size}</p>
                          <p className="text-xs text-gray-500 dark:text-gray-300">{formatTimestamp(item.mtime)}</p>
                        </button>
                      </li>
                    );
                  })}
                </ul>
              )}
            </div>
          </div>

          <div className="lg:col-span-2 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden min-h-[320px]">
            <div className="px-3 py-2 bg-gray-100 dark:bg-gray-700 text-sm font-semibold text-gray-700 dark:text-gray-100">
              {selectedFile ? `Preview: ${selectedFile}` : 'Select a file to preview'}
            </div>
            <div className="p-3">
              {loadingContent ? (
                <p className="text-sm text-gray-500 dark:text-gray-300">Loading file content...</p>
              ) : contentError ? (
                <p className="text-sm text-red-700 dark:text-red-300">{contentError}</p>
              ) : selectedFile ? (
                <div className="text-xs sm:text-sm bg-gray-900 text-gray-100 rounded-md p-3 max-h-[420px] overflow-auto whitespace-pre-wrap break-words font-mono">
                  {logContent ? (
                    <div
                      dangerouslySetInnerHTML={{ __html: convertAnsiToHtml(logContent) }}
                      style={{ whiteSpace: 'pre-wrap' }}
                    />
                  ) : (
                    'No content available.'
                  )}
                </div>
              ) : (
                <p className="text-sm text-gray-500 dark:text-gray-300">
                  Choose a file from the list to read its content.
                </p>
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
