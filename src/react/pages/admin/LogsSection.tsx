import React from 'react';
import { createUrl } from '../../utils/url';
import { LogEntry, LogsResponse } from '../../../../types/php_backend/logs';
import { Pagination } from '../../components/Pagination';
import DetailsCell from '../../components/DetailsCell';

interface LogsResponseWithExtras extends LogsResponse {
  [key: string]: any;
  logs: (LogEntry & { [key: string]: any })[];
}

export default function LogsSection() {
  const [logsData, setLogsData] = React.useState<LogsResponseWithExtras | null>(null);
  const [page, setPage] = React.useState(1);
  const perPage = 50;
  const [loading, setLoading] = React.useState(false);
  const prevLogsRef = React.useRef<any>(null);

  let isFetching = false;
  const fetchLogs = React.useCallback((pageNum: number) => {
    if (isFetching) return;
    isFetching = true;
    setLoading(true);
    const url = createUrl('/php_backend/logs.php', { page: pageNum, per_page: perPage });
    fetch(url)
      .then((response) => response.json())
      .then((data) => {
        if (JSON.stringify(data) !== JSON.stringify(prevLogsRef.current)) {
          setLogsData(data);
          prevLogsRef.current = data;
        }
      })
      .catch((error) => {
        console.error('Error fetching logs:', error);
      })
      .finally(() => {
        setLoading(false);
        isFetching = false;
      });
  }, []);

  // Fetch once on mount. Page changes will trigger fetch via `handlePageChange`.
  React.useEffect(() => {
    fetchLogs(page);
  }, []);

  // Automatic polling removed. Use manual Refresh button or pagination to fetch.

  const handlePageChange = (newPage: number) => {
    setPage(newPage);
    fetchLogs(newPage);
  };

  const logs = logsData?.logs || [];

  // Collect all unique keys from all logs for table headers
  // Keys to exclude from the table
  const excludeKeys: string[] = ['id'];

  const allKeys = React.useMemo(() => {
    const keysSet = new Set<string>();
    logs.forEach((log) => {
      Object.keys(log).forEach((k) => keysSet.add(k));
    });
    return Array.from(keysSet).filter((k) => !excludeKeys.includes(k));
  }, [logs]);

  return (
    <div className="flex flex-col items-center justify-center m-4 transition-colors">
      <div className="w-full bg-white dark:bg-gray-800 rounded-lg shadow-lg p-8 transition-colors">
        <div className="flex items-center justify-between mb-6">
          <h1 className="text-2xl font-bold text-center flex items-center gap-2 text-blue-700 dark:text-blue-300">
            <i className="fa-duotone fa-clipboard-list text-green-500 dark:text-green-400"></i>
            Log Activity
          </h1>
          <button
            className="ml-4 px-3 py-1 bg-blue-500 hover:bg-blue-600 text-white text-xs font-semibold rounded shadow transition-colors flex items-center gap-1"
            onClick={() => fetchLogs(page)}
            disabled={loading}
            title="Refresh logs now">
            <i className="fa fa-refresh"></i>
            <span className="hidden sm:inline">Refresh</span>
          </button>
        </div>
        <div className="overflow-x-auto max-h-[350px]">
          <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead>
              <tr>
                {allKeys.map((key) => (
                  <th
                    key={key}
                    className="sticky top-0 z-10 px-2 py-1 text-xs font-semibold text-gray-700 dark:text-gray-200 bg-gray-100 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-700 whitespace-nowrap"
                    scope="col">
                    {key
                      .split('_')
                      .map((part) =>
                        part.toLowerCase() === 'ip' ? 'IP' : part.charAt(0).toUpperCase() + part.slice(1)
                      )
                      .join(' ')}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody className="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
              {loading ? (
                <tr>
                  <td colSpan={allKeys.length} className="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                    Loading logs...
                  </td>
                </tr>
              ) : logs.length === 0 ? (
                <tr>
                  <td colSpan={allKeys.length} className="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                    No logs found.
                  </td>
                </tr>
              ) : (
                logs.map((log, idx) => (
                  <tr key={`${log.id ?? 'noid'}-${idx}`}>
                    {allKeys.map((key) => {
                      const value = log[key];
                      const isObject = typeof value === 'object' && value !== null;
                      const isLongString = typeof value === 'string' && value.length > 120;
                      return (
                        <td
                          key={key}
                          className="px-2 py-1 whitespace-pre-wrap text-xs text-gray-900 dark:text-gray-100 border-b border-gray-200 dark:border-gray-700 max-w-[80ch]">
                          {key === 'user_agent' && value ? (
                            <DetailsCell
                              raw={String(value)}
                              showCopy={false}
                              previewLength={40}
                              oneLinePreviewLength={40}
                            />
                          ) : isObject ? (
                            <DetailsCell raw={JSON.stringify(value)} />
                          ) : isLongString ? (
                            <DetailsCell raw={String(value)} />
                          ) : value !== undefined && value !== null ? (
                            String(value)
                          ) : (
                            ''
                          )}
                        </td>
                      );
                    })}
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
        <Pagination
          page={logsData?.page || 1}
          perPage={logsData?.per_page || perPage}
          count={logsData?.count || 0}
          onPageChange={handlePageChange}
        />
      </div>
    </div>
  );
}
