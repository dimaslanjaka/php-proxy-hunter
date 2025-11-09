import React from 'react';
import { useTranslation } from 'react-i18next';
import { LogEntry } from '../../../../types/php_backend/logs';
import { getUserInfo, getUserLogs } from '../../utils/user';
import DetailsCell from '../../components/DetailsCell';

export default function UserActivityCard() {
  const { t } = useTranslation();

  const [logs, setLogs] = React.useState<LogEntry[]>([]);
  const [loading, setLoading] = React.useState(true);
  const [error, setError] = React.useState<string | null>(null);
  const [user, setUser] = React.useState<string>('');
  const [page, setPage] = React.useState(1);
  const [pageSize, setPageSize] = React.useState(10);
  const [total, setTotal] = React.useState<number | null>(null);

  // Helper to normalize total count from API response
  const extractTotalFromData = (data: any): number | null => {
    if (data && typeof data.count === 'number') return data.count;
    if (data && Array.isArray(data.logs)) return data.logs.length;
    return null;
  };

  // mounted ref so manual refresh won't update state after unmount
  const isMountedRef = React.useRef(true);

  React.useEffect(() => {
    isMountedRef.current = true;
    setLoading(true);
    getUserInfo().then((info) => {
      if (isMountedRef.current) setUser(info.email || info.username || '');
    });
    // Fetch once on mount. Pagination and page size changes will trigger explicit fetches via handlers.
    getUserLogs(page, pageSize)
      .then((data) => {
        if (isMountedRef.current) {
          setLogs(data.logs || []);
          setTotal(extractTotalFromData(data));
        }
      })
      .catch(() => isMountedRef.current && setError(t('failed_to_fetch_activity')))
      .finally(() => isMountedRef.current && setLoading(false));
    return () => {
      isMountedRef.current = false;
    };
  }, [t]);

  // Manual refresh handler to re-fetch logs for the current page/size
  function handleRefresh() {
    setLoading(true);
    setError(null);
    getUserLogs(page, pageSize)
      .then((data) => {
        if (isMountedRef.current) {
          setLogs(data.logs || []);
          setTotal(extractTotalFromData(data));
        }
      })
      .catch(() => isMountedRef.current && setError(t('failed_to_fetch_activity')))
      .finally(() => isMountedRef.current && setLoading(false));
  }

  const totalPages = total ? Math.ceil(total / pageSize) : null;
  const canPrev = page > 1;
  const canNext = totalPages ? page < totalPages : logs.length === pageSize;

  function handlePrev() {
    if (canPrev) {
      const newPage = page - 1;
      setPage(newPage);
      setLoading(true);
      getUserLogs(newPage, pageSize)
        .then((data) => {
          if (isMountedRef.current) {
            setLogs(data.logs || []);
            setTotal(
              typeof (data as any).count === 'number'
                ? (data as any).count
                : Array.isArray(data.logs)
                  ? data.logs.length
                  : null
            );
          }
        })
        .catch(() => isMountedRef.current && setError(t('failed_to_fetch_activity')))
        .finally(() => isMountedRef.current && setLoading(false));
    }
  }
  function handleNext() {
    if (canNext) {
      const newPage = page + 1;
      setPage(newPage);
      setLoading(true);
      getUserLogs(newPage, pageSize)
        .then((data) => {
          if (isMountedRef.current) {
            setLogs(data.logs || []);
            setTotal(
              typeof (data as any).count === 'number'
                ? (data as any).count
                : Array.isArray(data.logs)
                  ? data.logs.length
                  : null
            );
          }
        })
        .catch(() => isMountedRef.current && setError(t('failed_to_fetch_activity')))
        .finally(() => isMountedRef.current && setLoading(false));
    }
  }
  function handlePageSizeChange(e: React.ChangeEvent<HTMLSelectElement>) {
    const newSize = Number(e.target.value);
    setPageSize(newSize);
    // reset to page 1 and fetch for new page size
    setPage(1);
    setLoading(true);
    getUserLogs(1, newSize)
      .then((data) => {
        if (isMountedRef.current) {
          setLogs(data.logs || []);
          setTotal(extractTotalFromData(data));
        }
      })
      .catch(() => isMountedRef.current && setError(t('failed_to_fetch_activity')))
      .finally(() => isMountedRef.current && setLoading(false));
  }

  // Define columns for consistent header and body order
  const columns = [
    { key: 'action', label: t('action') },
    { key: 'timestamp', label: t('timestamp') },
    { key: 'details', label: t('details') },
    { key: 'ip_address', label: 'IP' },
    { key: 'user_agent', label: 'UA' }
  ];

  // Helper to extract values for each column from a log entry
  function getColumnValue(log: LogEntry, colKey: string) {
    switch (colKey) {
      case 'action':
        if ('action_type' in log && typeof log.action_type === 'string' && log.action_type) {
          return log.action_type;
        } else if ('action' in log && typeof (log as any).action === 'string' && (log as any).action) {
          return (log as any).action;
        } else if ('message' in log && typeof (log as any).message === 'string' && (log as any).message) {
          return (log as any).message;
        }
        return '-';
      case 'timestamp':
        if ('created_at' in log && typeof (log as any).created_at === 'string' && (log as any).created_at) {
          return (log as any).created_at;
        } else if ('timestamp' in log && typeof (log as any).timestamp === 'string' && (log as any).timestamp) {
          return (log as any).timestamp;
        } else if ('log_time' in log && typeof (log as any).log_time === 'string' && (log as any).log_time) {
          return (log as any).log_time;
        }
        return '-';
      case 'details':
        // Details may be long JSON or plain text. We'll render using a dedicated cell component below.
        if ('details' in log && typeof (log as any).details === 'string' && (log as any).details) {
          return <DetailsCell raw={(log as any).details} />;
        } else if ('extra_info' in log && typeof (log as any).extra_info === 'string' && (log as any).extra_info) {
          return <DetailsCell raw={(log as any).extra_info} />;
        }
        return '-';
      case 'ip_address':
        return (log as any).ip_address || '-';
      case 'user_agent':
        if ((log as any).user_agent) {
          return (
            <DetailsCell raw={(log as any).user_agent} showCopy={false} previewLength={40} oneLinePreviewLength={40} />
          );
        }
        return '-';
      default:
        return '-';
    }
  }

  return (
    <div className="w-full px-2 sm:px-4 md:px-6 lg:px-8 transition-colors">
      <div className="w-full bg-white dark:bg-gray-800 rounded-lg shadow-lg p-4 sm:p-6 transition-colors dark:border dark:border-gray-700 min-w-0">
        <div className="flex items-start justify-between mb-6 gap-3">
          <h1 className="text-2xl font-bold flex-1 min-w-0 flex items-center gap-2 text-blue-700 dark:text-blue-300 break-words whitespace-normal">
            <i className="fa-duotone fa-list-check text-green-500 dark:text-green-400 flex-shrink-0"></i>
            <span className="truncate">{t('user_activity_title', { user })}</span>
          </h1>
          <button
            className="ml-0 sm:ml-4 px-3 py-1 bg-blue-500 hover:bg-blue-600 text-white text-xs font-semibold rounded shadow transition-colors flex items-center gap-1 flex-shrink-0"
            onClick={handleRefresh}
            disabled={loading}
            title={t('refresh_logs_now') || 'Refresh activity'}
            aria-label={t('refresh')}>
            <i className="fa fa-refresh sm:hidden" aria-hidden="true"></i>
            <span className="hidden sm:inline">{t('refresh')}</span>
          </button>
        </div>
        {loading ? (
          <div className="text-center text-gray-500 dark:text-gray-400">{t('loading')}</div>
        ) : error ? (
          <div className="text-center text-red-600 dark:text-red-400">{error}</div>
        ) : logs.length === 0 ? (
          <div className="text-center text-gray-500 dark:text-gray-400">{t('no_activity_found')}</div>
        ) : (
          <>
            <div
              className="w-full max-w-full block relative overflow-x-auto max-h-[350px]"
              style={{ WebkitOverflowScrolling: 'touch' }}>
              {/* ensure table can grow and be horizontally scrolled inside the wrapper */}
              <table className="inline-table table-auto divide-y divide-gray-200 dark:divide-gray-700">
                <thead>
                  <tr>
                    {columns.map((col) => (
                      <th
                        key={col.key}
                        scope="col"
                        className="sticky top-0 z-10 px-2 py-1 text-xs font-semibold text-gray-700 dark:text-gray-200 bg-gray-100 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-700 text-left">
                        {col.label}
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody className="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                  {logs.map((log) => (
                    <tr key={log.id}>
                      {columns.map((col) => {
                        const isLongData = col.key === 'details' || col.key === 'user_agent';
                        const tdClass = isLongData
                          ? 'px-2 py-1 align-top text-xs text-gray-900 dark:text-gray-100 border-b border-gray-200 dark:border-gray-700 max-w-full break-words'
                          : 'px-2 py-1 whitespace-pre-wrap text-xs text-gray-900 dark:text-gray-100 border-b border-gray-200 dark:border-gray-700';
                        const innerClass = isLongData ? 'break-words whitespace-pre-wrap' : '';
                        return (
                          <td key={col.key} className={tdClass}>
                            <div className={innerClass}>{getColumnValue(log, col.key)}</div>
                          </td>
                        );
                      })}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <div className="flex flex-col md:flex-row md:items-center md:justify-between mt-4 gap-2">
              <div className="flex items-center gap-2">
                <button
                  className="px-3 py-1 rounded bg-primary-100 dark:bg-gray-900 text-primary-700 dark:text-white border border-primary-200 dark:border-gray-700 focus:ring-2 focus:ring-primary-500 focus:outline-none disabled:opacity-50 flex items-center gap-1"
                  onClick={handlePrev}
                  disabled={!canPrev}
                  aria-label={t('prev')}>
                  <i className="fa fa-chevron-left sm:hidden" aria-hidden="true"></i>
                  <span className="hidden sm:inline">{t('prev')}</span>
                </button>
                <span className="mx-2 text-gray-700 dark:text-white">
                  {t('page')} {page}
                  {totalPages ? ` / ${totalPages}` : ''}
                </span>
                <button
                  className="px-3 py-1 rounded bg-primary-100 dark:bg-gray-900 text-primary-700 dark:text-white border border-primary-200 dark:border-gray-700 focus:ring-2 focus:ring-primary-500 focus:outline-none disabled:opacity-50 flex items-center gap-1"
                  onClick={handleNext}
                  disabled={!canNext}
                  aria-label={t('next')}>
                  <i className="fa fa-chevron-right sm:hidden" aria-hidden="true"></i>
                  <span className="hidden sm:inline">{t('next')}</span>
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
