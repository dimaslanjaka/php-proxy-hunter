import React from 'react';
import { useTranslation } from 'react-i18next';
import { LogEntry } from '../../../../types/php_backend/logs';
import { getUserInfo, getUserLogs } from '../../utils/user';

export default function UserActivityCard() {
  const { t } = useTranslation();

  const [logs, setLogs] = React.useState<LogEntry[]>([]);
  const [loading, setLoading] = React.useState(true);
  const [error, setError] = React.useState<string | null>(null);
  const [user, setUser] = React.useState<string>('');
  const [page, setPage] = React.useState(1);
  const [pageSize, setPageSize] = React.useState(10);
  const [total, setTotal] = React.useState<number | null>(null);

  React.useEffect(() => {
    let isMounted = true;
    setLoading(true);
    getUserInfo().then((info) => {
      if (isMounted) setUser(info.email || info.username || '');
    });
    getUserLogs(page, pageSize)
      .then((data) => {
        if (isMounted) {
          setLogs(data.logs || []);
          // Try to get count from data.count, fallback to logs.length if not present
          setTotal(
            typeof (data as any).count === 'number'
              ? (data as any).count
              : Array.isArray(data.logs)
                ? data.logs.length
                : null
          );
        }
      })
      .catch(() => isMounted && setError(t('failed_to_fetch_activity')))
      .finally(() => isMounted && setLoading(false));
    return () => {
      isMounted = false;
    };
  }, [t, page, pageSize]);

  const totalPages = total ? Math.ceil(total / pageSize) : null;
  const canPrev = page > 1;
  const canNext = totalPages ? page < totalPages : logs.length === pageSize;

  function handlePrev() {
    if (canPrev) setPage((p) => p - 1);
  }
  function handleNext() {
    if (canNext) setPage((p) => p + 1);
  }
  function handlePageSizeChange(e: React.ChangeEvent<HTMLSelectElement>) {
    setPageSize(Number(e.target.value));
    setPage(1);
  }

  return (
    <div className="flex flex-col items-center justify-center m-4 transition-colors">
      <div className="w-full bg-white dark:bg-gray-800 rounded-lg shadow-lg p-8 transition-colors dark:border dark:border-gray-700">
        <h1 className="text-2xl font-bold mb-6 text-center flex items-center justify-center gap-2 text-blue-700 dark:text-blue-300">
          <i className="fa-duotone fa-list-check text-green-500 dark:text-green-400"></i>
          {t('user_activity_title', { user })}
        </h1>
        {loading ? (
          <div className="text-center text-gray-500 dark:text-gray-400">{t('loading')}</div>
        ) : error ? (
          <div className="text-center text-red-600 dark:text-red-400">{error}</div>
        ) : logs.length === 0 ? (
          <div className="text-center text-gray-500 dark:text-gray-400">{t('no_activity_found')}</div>
        ) : (
          <>
            <div className="overflow-x-auto">
              <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700 table-auto text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400 border border-gray-200 dark:border-gray-700">
                <thead className="text-xs uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                  <tr>
                    <th scope="col" className="sticky top-0 z-10 px-4 py-2 font-medium bg-gray-50 dark:bg-gray-700">
                      {t('action')}
                    </th>
                    <th scope="col" className="sticky top-0 z-10 px-4 py-2 font-medium bg-gray-50 dark:bg-gray-700">
                      {t('timestamp')}
                    </th>
                    <th scope="col" className="sticky top-0 z-10 px-4 py-2 font-medium bg-gray-50 dark:bg-gray-700">
                      {t('details')}
                    </th>
                  </tr>
                </thead>
              </table>
              <div className="max-h-[350px] overflow-y-auto">
                <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700 table-auto text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400 border border-gray-200 dark:border-gray-700">
                  <tbody className="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    {logs.map((log) => {
                      let action: string = '-';
                      let timestamp: string = '-';
                      let details: string = '-';
                      if ('action_type' in log && typeof log.action_type === 'string' && log.action_type) {
                        action = log.action_type;
                      } else if ('action' in log && typeof (log as any).action === 'string' && (log as any).action) {
                        action = (log as any).action;
                      } else if ('message' in log && typeof (log as any).message === 'string' && (log as any).message) {
                        action = (log as any).message;
                      }
                      if (
                        'created_at' in log &&
                        typeof (log as any).created_at === 'string' &&
                        (log as any).created_at
                      ) {
                        timestamp = (log as any).created_at;
                      } else if (
                        'timestamp' in log &&
                        typeof (log as any).timestamp === 'string' &&
                        (log as any).timestamp
                      ) {
                        timestamp = (log as any).timestamp;
                      } else if (
                        'log_time' in log &&
                        typeof (log as any).log_time === 'string' &&
                        (log as any).log_time
                      ) {
                        timestamp = (log as any).log_time;
                      }
                      if ('details' in log && typeof (log as any).details === 'string' && (log as any).details) {
                        details = (log as any).details;
                      } else if (
                        'extra_info' in log &&
                        typeof (log as any).extra_info === 'string' &&
                        (log as any).extra_info
                      ) {
                        details = (log as any).extra_info;
                      }
                      return (
                        <tr key={log.id}>
                          <td className="px-4 py-2 font-semibold text-gray-900 dark:text-white">{action}</td>
                          <td className="px-4 py-2 text-xs text-gray-500 dark:text-gray-400">{timestamp}</td>
                          <td className="px-4 py-2 text-sm text-gray-700 dark:text-gray-300">{details}</td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            </div>
            <div className="flex flex-col md:flex-row md:items-center md:justify-between mt-4 gap-2">
              <div className="flex items-center gap-2">
                <button
                  className="px-3 py-1 rounded bg-primary-100 dark:bg-gray-900 text-primary-700 dark:text-white border border-primary-200 dark:border-gray-700 focus:ring-2 focus:ring-primary-500 focus:outline-none disabled:opacity-50"
                  onClick={handlePrev}
                  disabled={!canPrev}>
                  {t('prev')}
                </button>
                <span className="mx-2 text-gray-700 dark:text-white">
                  {t('page')} {page}
                  {totalPages ? ` / ${totalPages}` : ''}
                </span>
                <button
                  className="px-3 py-1 rounded bg-primary-100 dark:bg-gray-900 text-primary-700 dark:text-white border border-primary-200 dark:border-gray-700 focus:ring-2 focus:ring-primary-500 focus:outline-none disabled:opacity-50"
                  onClick={handleNext}
                  disabled={!canNext}>
                  {t('next')}
                </button>
              </div>
              <div className="flex items-center gap-2">
                <label htmlFor="pageSize" className="text-xs text-gray-700 dark:text-white">
                  {t('rows_per_page')}
                </label>
                <select
                  id="pageSize"
                  value={pageSize}
                  onChange={handlePageSizeChange}
                  className="px-2 py-1 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-700 dark:text-white focus:ring-2 focus:ring-primary-500 focus:outline-none">
                  {[10, 20, 50, 100].map((size) => (
                    <option key={size} value={size}>
                      {size}
                    </option>
                  ))}
                </select>
              </div>
            </div>
          </>
        )}
      </div>
    </div>
  );
}
