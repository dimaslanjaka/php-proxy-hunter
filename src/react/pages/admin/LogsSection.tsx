import React from 'react';
import { createUrl } from '../../utils/url';
import { LogsResponse } from '../../../../types/php_backend/logs';
import { Pagination } from '../../components/Pagination';

interface LogsResponseWithExtras extends LogsResponse {
  [key: string]: any;
  logs: (LogsResponse['logs'][number] & { [key: string]: any })[];
}

export default function LogsSection() {
  const [logsData, setLogsData] = React.useState<LogsResponseWithExtras | null>(null);
  const [page, setPage] = React.useState(1);
  const perPage = 50;
  const [loading, setLoading] = React.useState(false);

  let isFetching = false;
  const fetchLogs = React.useCallback((pageNum: number) => {
    if (isFetching) return;
    isFetching = true;
    setLoading(true);
    const url = createUrl('/php_backend/logs.php', { page: pageNum, per_page: perPage });
    fetch(url)
      .then((response) => response.json())
      .then((data) => {
        setLogsData(data);
      })
      .catch((error) => {
        console.error('Error fetching logs:', error);
      })
      .finally(() => {
        setLoading(false);
        isFetching = false;
      });
  }, []);

  React.useEffect(() => {
    fetchLogs(page);
  }, [page, fetchLogs]);

  // Fetch logs every 30 seconds
  React.useEffect(() => {
    const interval = setInterval(() => {
      fetchLogs(page);
    }, 30000);
    return () => clearInterval(interval);
  }, [page, fetchLogs]);

  const logs = logsData?.logs || [];

  // Collect all unique keys from all logs for table headers
  // Keys to exclude from the table
  const excludeKeys = ['user_id', 'package_id', 'user_id_real', 'package_id_real', 'id', 'log_level', 'log_type'];

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
        <h1 className="text-2xl font-bold mb-6 text-center flex items-center justify-center gap-2 text-blue-700 dark:text-blue-300">
          <i className="fa-duotone fa-clipboard-list text-green-500 dark:text-green-400"></i>
          Log Activity
        </h1>
        <div className="overflow-x-auto" style={{ maxHeight: '350px', overflowY: 'auto' }}>
          <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead>
              <tr>
                {allKeys.map((key) => (
                  <th
                    key={key}
                    className="px-2 py-1 text-xs font-semibold text-gray-700 dark:text-gray-200 bg-gray-100 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-700 whitespace-nowrap">
                    {key}
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
                    {allKeys.map((key) => (
                      <td
                        key={key}
                        className="px-2 py-1 whitespace-pre-wrap text-xs text-gray-900 dark:text-gray-100 border-b border-gray-200 dark:border-gray-700">
                        {typeof log[key] === 'object' && log[key] !== null
                          ? JSON.stringify(log[key])
                          : log[key] !== undefined && log[key] !== null
                            ? String(log[key])
                            : ''}
                      </td>
                    ))}
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
          onPageChange={setPage}
        />
      </div>
    </div>
  );
}
