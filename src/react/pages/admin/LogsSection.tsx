import React from 'react';
import { createUrl } from '../../utils/url';
import { LogsResponse } from '../../../../types/php_backend/logs';
import { Pagination } from '../../components/Pagination';

export default function LogsSection() {
  const [logsData, setLogsData] = React.useState<LogsResponse | null>(null);
  const [page, setPage] = React.useState(1);
  const perPage = 50;
  const [loading, setLoading] = React.useState(false);

  const fetchLogs = React.useCallback((pageNum: number) => {
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
      });
  }, []);

  React.useEffect(() => {
    fetchLogs(page);
  }, [page, fetchLogs]);

  const logs = logsData?.logs || [];

  return (
    <div className="flex flex-col items-center justify-center m-4 transition-colors">
      <div className="w-full bg-white dark:bg-gray-800 rounded-lg shadow-lg p-8 transition-colors">
        <h1 className="text-2xl font-bold mb-6 text-center flex items-center justify-center gap-2 text-blue-700 dark:text-blue-300">
          <i className="fa-duotone fa-clipboard-list text-green-500 dark:text-green-400"></i>
          Log Activity
        </h1>
        <div className="overflow-x-auto" style={{ maxHeight: '350px', overflowY: 'auto' }}>
          <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <tbody className="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
              {loading ? (
                <tr>
                  <td className="px-6 py-4 text-center text-gray-500 dark:text-gray-400">Loading logs...</td>
                </tr>
              ) : logs.length === 0 ? (
                <tr>
                  <td className="px-6 py-4 text-center text-gray-500 dark:text-gray-400">No logs found.</td>
                </tr>
              ) : (
                logs.map((log: any) => {
                  // Handle both UserLog and PackageLog
                  let user = '';
                  let action = '';
                  let time = '';
                  if ('user_id' in log) {
                    user = log.user_email ? log.user_email : '';
                    action = log.message || log.log_type || log.log_level || '';
                    time = log.log_time || log.timestamp || '';
                  } else if ('package_id' in log) {
                    // Show package name (package code) if available
                    const pkgName = log.package_name ? log.package_name : `Package #${log.package_id}`;
                    let pkgCode = '';
                    // Try to extract code from details JSON if present
                    if (log.details) {
                      try {
                        const details = JSON.parse(log.details);
                        if (details.code) {
                          pkgCode = details.code;
                        }
                      } catch {
                        // Ignore JSON parse errors
                      }
                    }
                    user = pkgCode ? `${pkgName} (${pkgCode})` : pkgName;
                    action = log.action || '';
                    time = log.log_time || log.created_at || '';
                  }
                  // Show each data in its own <td>
                  return (
                    <tr key={log.id}>
                      <td className="px-2 py-1 whitespace-pre-wrap text-xs text-gray-900 dark:text-gray-100">{user}</td>
                      <td className="px-2 py-1 whitespace-pre-wrap text-xs text-gray-900 dark:text-gray-100">
                        {action}
                      </td>
                      <td className="px-2 py-1 whitespace-pre-wrap text-xs text-gray-500 dark:text-gray-400">{time}</td>
                    </tr>
                  );
                })
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
