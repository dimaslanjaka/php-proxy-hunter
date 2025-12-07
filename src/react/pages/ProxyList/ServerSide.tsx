import React from 'react';
import { useTranslation } from 'react-i18next';
import { createUrl } from '../../utils/url';
import { timeAgo } from '../../../utils/date/timeAgo.js';
import { noop } from '../../../utils/other';
import { getProxyTypeColorClass } from '../../utils/proxyTypeColors';

type ProxyRow = Record<string, any>;

export default function ServerSide() {
  const { t } = useTranslation();
  const [rows, setRows] = React.useState<ProxyRow[]>([]);
  const [page, setPage] = React.useState(1);
  const [perPage, setPerPage] = React.useState(10);
  const [search, setSearch] = React.useState('');
  // Default to 'active' so initial view shows active proxies
  const [statuses, setStatuses] = React.useState<string[]>(['active']);
  const [statusFilter, setStatusFilter] = React.useState('active');
  const [draw, setDraw] = React.useState(0);
  const [recordsTotal, setRecordsTotal] = React.useState(0);
  const [recordsFiltered, setRecordsFiltered] = React.useState(0);
  const [loading, setLoading] = React.useState(false);

  const fetchData = React.useCallback(async () => {
    setLoading(true);
    try {
      const start = (page - 1) * perPage;

      // Use POST to avoid URL-length or nested param parsing issues
      const body = new URLSearchParams();
      body.append('draw', String(draw + 1));
      body.append('start', String(start));
      body.append('length', String(perPage));
      if (search && search.length > 0) {
        // DataTables nested param name
        body.append('search[value]', search);
      }
      if (statusFilter && statusFilter.length > 0) {
        body.append('status', statusFilter);
      }

      const url = createUrl('/php_backend/proxy-list.php');
      const res = await fetch(url, { method: 'POST', body, cache: 'no-store' });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      if (data?.error) {
        console.error('Backend error', data.error, data);
        // show empty rows but surface the error in console
        setRows([]);
        setRecordsTotal(0);
        setRecordsFiltered(0);
      } else {
        setDraw((d) => d + 1);
        setRows(Array.isArray(data.data) ? data.data : []);
        setRecordsTotal(Number(data.recordsTotal) || 0);
        setRecordsFiltered(Number(data.recordsFiltered) || 0);
      }
    } catch (err) {
      console.error('Failed to fetch proxy list', err);
    } finally {
      setLoading(false);
    }
  }, [page, perPage, search, statusFilter]);

  React.useEffect(() => {
    fetchData().catch(noop);
  }, [fetchData]);

  // Fetch available distinct statuses for filter dropdown
  React.useEffect(() => {
    let mounted = true;
    (async () => {
      try {
        const body = new URLSearchParams();
        body.append('get_statuses', '1');
        const res = await fetch(createUrl('/php_backend/proxy-list.php'), { method: 'POST', body });
        if (!res.ok) return;
        const data = await res.json();
        if (!mounted) return;
        if (Array.isArray(data.statuses)) setStatuses(data.statuses.filter(Boolean));
      } catch (_e) {
        // ignore
      }
    })();
    return () => {
      mounted = false;
    };
  }, []);

  const totalPages = perPage > 0 ? Math.max(1, Math.ceil(recordsFiltered / perPage)) : 1;

  function formatLatency(value: any) {
    if (value === null || value === undefined) return '-';
    const s = String(value).trim();
    if (s === '' || s === '-' || s.toLowerCase() === 'n/a') return '-';
    // replace comma decimal separator with dot, then parse
    const parsed = Number(s.replace(/,/g, '.'));
    if (Number.isNaN(parsed)) return s;
    return `${Math.round(parsed)} ms`;
  }

  return (
    <section className="my-6 mx-2">
      <div className="bg-white dark:bg-gray-900 rounded-xl shadow-lg p-4 border border-blue-200 dark:border-blue-700 flowbite-modal">
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-3 gap-3">
          <h2 className="text-lg font-semibold text-blue-800 dark:text-blue-200">{t('Proxy List (Server)')}</h2>
          <div className="flex flex-col sm:flex-row sm:items-center gap-2 w-full sm:w-auto">
            <input
              type="search"
              placeholder={t('Search proxies') as string}
              value={search}
              onChange={(e) => {
                setSearch(e.target.value);
                setPage(1); // reset to first page when search changes
              }}
              onKeyDown={(e) => {
                if (e.key === 'Enter') setPage(1);
              }}
              className="px-2 py-1 border rounded-md text-sm w-full sm:w-auto"
            />
            <select
              value={statusFilter}
              onChange={(e) => {
                setStatusFilter(e.target.value);
                setPage(1);
              }}
              className="px-2 py-1 border rounded-md text-sm w-full sm:w-auto min-w-[120px]">
              <option value="">All statuses</option>
              {statuses.map((s) => (
                <option key={s} value={s}>
                  {s}
                </option>
              ))}
            </select>
            <select
              value={perPage}
              onChange={(e) => {
                setPerPage(Number(e.target.value));
                setPage(1);
              }}
              className="px-2 py-1 border rounded-md text-sm w-full sm:w-auto min-w-[80px]">
              {[10, 25, 50, 100].map((n) => (
                <option key={n} value={n}>
                  {n}
                </option>
              ))}
            </select>
            <div className="w-full sm:w-auto">
              <button
                onClick={() => {
                  setPage(1);
                  fetchData().catch(noop);
                }}
                className="px-3 py-1 bg-blue-600 text-white rounded-md text-sm w-full sm:w-auto">
                {loading ? 'Loading...' : t('Refresh')}
              </button>
            </div>
          </div>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-sm text-left text-gray-500 dark:text-gray-400 table-auto">
            <thead>
              <tr>
                <th className="px-2 py-1 text-gray-700 dark:text-gray-200 whitespace-nowrap">Proxy</th>
                <th className="px-2 py-1 text-gray-700 dark:text-gray-200 whitespace-nowrap">Status</th>
                <th className="px-2 py-1 text-gray-700 dark:text-gray-200 whitespace-nowrap">Type</th>
                <th className="px-2 py-1 text-gray-700 dark:text-gray-200 whitespace-nowrap">Country</th>
                <th className="px-2 py-1 text-gray-700 dark:text-gray-200 whitespace-nowrap">City</th>
                <th className="px-2 py-1 text-gray-700 dark:text-gray-200 whitespace-nowrap">Latency</th>
                <th className="px-2 py-1 text-gray-700 dark:text-gray-200 whitespace-nowrap">Last Check</th>
              </tr>
            </thead>
            <tbody>
              {rows.length === 0 ? (
                <tr>
                  <td colSpan={7} className="px-2 py-4 text-center text-gray-500 dark:text-gray-400">
                    {loading ? (
                      <span className="text-gray-700 dark:text-gray-200">Loading...</span>
                    ) : (
                      <span className="text-gray-700 dark:text-gray-200">No proxies</span>
                    )}
                  </td>
                </tr>
              ) : (
                rows.map((r, i) => (
                  <tr key={i} className="odd:bg-gray-50 dark:odd:bg-gray-800">
                    <td className="px-2 py-1 text-gray-900 dark:text-gray-100 whitespace-nowrap">{r.proxy}</td>
                    <td className="px-2 py-1 whitespace-nowrap">
                      {(() => {
                        const s = String(r.status || '').toLowerCase();
                        let cls =
                          'border border-gray-300 text-gray-800 dark:border-gray-700 dark:text-gray-200 bg-transparent';
                        if (s === 'dead' || s === 'port-closed') {
                          cls =
                            'border border-red-500 text-red-600 dark:border-red-400 dark:text-red-400 bg-transparent';
                        } else if (s === 'active') {
                          cls =
                            'border border-green-500 text-green-600 dark:border-green-400 dark:text-green-400 bg-transparent';
                        } else if (s === 'port-open' || s === 'untested') {
                          cls =
                            'border border-yellow-500 text-yellow-700 dark:border-yellow-400 dark:text-yellow-300 bg-transparent';
                        }
                        return (
                          <span className={`inline-block px-2 py-0.5 text-xs font-medium rounded ${cls}`}>
                            {r.status || '-'}
                          </span>
                        );
                      })()}
                    </td>
                    <td className="px-2 py-1 text-gray-900 dark:text-gray-100 whitespace-nowrap">
                      {r.type
                        ? String(r.type)
                            .split('-')
                            .filter(Boolean)
                            .map((t) => {
                              const baseClass =
                                'inline-block rounded px-2 py-0.5 mx-0.5 mb-0.5 text-xxs font-semibold align-middle border mr-1';
                              const colorClass = getProxyTypeColorClass(t);
                              const badgeClass = `${baseClass} ${colorClass}`;
                              return (
                                <span key={t + badgeClass} className={badgeClass}>
                                  {t.toUpperCase()}
                                </span>
                              );
                            })
                        : '-'}
                    </td>
                    <td className="px-2 py-1 text-gray-900 dark:text-gray-100 whitespace-nowrap">{r.country}</td>
                    <td className="px-2 py-1 text-gray-900 dark:text-gray-100 whitespace-nowrap">{r.city}</td>
                    <td className="px-2 py-1 text-gray-900 dark:text-gray-100 whitespace-nowrap">
                      {formatLatency(r.latency)}
                    </td>
                    <td className="px-2 py-1 text-gray-900 dark:text-gray-100 whitespace-nowrap">
                      {r.last_check ? timeAgo(r.last_check) : '-'}
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between mt-3 gap-2">
          <div className="text-sm text-gray-600 dark:text-gray-400">{`Showing ${rows.length} of ${recordsFiltered} filtered (${recordsTotal} total)`}</div>
          <div className="flex items-center gap-2 flex-wrap">
            <button
              disabled={page <= 1}
              onClick={() => setPage(1)}
              className="px-2 py-1 border rounded disabled:opacity-50 bg-transparent text-gray-700 dark:text-gray-200 border-gray-300 dark:border-gray-700">
              First
            </button>
            <button
              disabled={page <= 1}
              onClick={() => setPage((p) => Math.max(1, p - 1))}
              className="px-2 py-1 border rounded disabled:opacity-50 bg-transparent text-gray-700 dark:text-gray-200 border-gray-300 dark:border-gray-700">
              Prev
            </button>

            {/* Numeric page buttons (compact window) - hide on xs */}
            <div className="hidden sm:flex items-center gap-1">
              {(() => {
                const maxButtons = 7;
                const pages: number[] = [];
                let start = Math.max(1, page - Math.floor(maxButtons / 2));
                let end = start + maxButtons - 1;
                if (end > totalPages) {
                  end = totalPages;
                  start = Math.max(1, end - maxButtons + 1);
                }
                for (let p = start; p <= end; p++) pages.push(p);
                return pages.map((p) => (
                  <button
                    key={p}
                    onClick={() => setPage(p)}
                    className={`px-2 py-1 rounded border ${p === page ? 'bg-blue-600 text-white' : 'bg-transparent text-gray-700 dark:text-gray-200 border-gray-300 dark:border-gray-700'}`}>
                    {p}
                  </button>
                ));
              })()}
            </div>

            <button
              disabled={page >= totalPages}
              onClick={() => setPage((p) => Math.min(totalPages, p + 1))}
              className="px-2 py-1 border rounded disabled:opacity-50 bg-transparent text-gray-700 dark:text-gray-200 border-gray-300 dark:border-gray-700">
              Next
            </button>
            <button
              disabled={page >= totalPages}
              onClick={() => setPage(totalPages)}
              className="px-2 py-1 border rounded disabled:opacity-50 bg-transparent text-gray-700 dark:text-gray-200 border-gray-300 dark:border-gray-700">
              Last
            </button>
          </div>
        </div>
      </div>
    </section>
  );
}
