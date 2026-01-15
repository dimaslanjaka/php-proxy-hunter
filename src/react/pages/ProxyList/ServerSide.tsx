import React from 'react';
import { useTranslation } from 'react-i18next';
import { createUrl } from '../../utils/url';
import { timeAgo } from '../../../utils/date/timeAgo.js';
import { noop } from '../../../utils/other';
import { getProxyTypeColorClass } from '../../utils/proxyColors';
import { formatLatency } from './utils';
import copyToClipboard from '../../../utils/data/copyToClipboard.js';

type ProxyRow = Record<string, any>;

type CounterProxies = {
  total_proxies?: number;
  working_proxies?: number;
  private_proxies?: number;
  https_proxies?: number;
  untested_proxies?: number;
  dead_proxies?: number;
};

export default function ServerSide() {
  const { t } = useTranslation();
  const [rows, setRows] = React.useState<ProxyRow[]>([]);
  const [errorMsg, setErrorMsg] = React.useState<string>('');
  const [page, setPage] = React.useState(1);
  const [perPage, setPerPage] = React.useState(10);
  const [search, setSearch] = React.useState('');
  // Default to 'active' so initial view shows active proxies
  const [statuses, setStatuses] = React.useState<string[]>(['active']);
  const [statusFilter, setStatusFilter] = React.useState('active');
  const [typeFilter, setTypeFilter] = React.useState('');
  const [draw, setDraw] = React.useState(0);
  const [recordsTotal, setRecordsTotal] = React.useState(0);
  const [recordsFiltered, setRecordsFiltered] = React.useState(0);
  const [counters, setCounters] = React.useState<CounterProxies>({});
  const [loading, setLoading] = React.useState(false);
  const [copiedIndex, setCopiedIndex] = React.useState<number | null>(null);
  const [serverDriver, setServerDriver] = React.useState<string>('');
  const [serverPage, setServerPage] = React.useState<number | null>(null);
  const [serverPerPage, setServerPerPage] = React.useState<number | null>(null);

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
      if (typeFilter && typeFilter.length > 0) {
        body.append('type', typeFilter);
      }

      const url = createUrl('/php_backend/proxy-list.php');
      const res = await fetch(url, { method: 'POST', body, cache: 'no-store' });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      if (data?.error) {
        // Surface backend error to the UI
        const msg = typeof data.error === 'string' ? data.error : JSON.stringify(data.error);
        console.error('Backend error', msg, data);
        setErrorMsg(msg || 'Server returned an error');
        // show empty rows
        setRows([]);
        setRecordsTotal(0);
        setRecordsFiltered(0);
      } else {
        setErrorMsg('');
        setDraw((d) => d + 1);
        setRows(Array.isArray(data.data) ? data.data : []);
        setRecordsTotal(Number(data.recordsTotal) || 0);
        setRecordsFiltered(Number(data.recordsFiltered) || 0);
        setServerDriver(typeof data.driver === 'string' ? data.driver : '');
        setServerPage(Number.isFinite(Number(data.page)) ? Number(data.page) : null);
        setServerPerPage(Number.isFinite(Number(data.perPage)) ? Number(data.perPage) : null);
        if (data.counter_proxies && typeof data.counter_proxies === 'object') {
          setCounters({
            total_proxies: Number(data.counter_proxies.total_proxies) || 0,
            working_proxies: Number(data.counter_proxies.working_proxies) || 0,
            private_proxies: Number(data.counter_proxies.private_proxies) || 0,
            https_proxies: Number(data.counter_proxies.https_proxies) || 0,
            untested_proxies: Number(data.counter_proxies.untested_proxies) || 0,
            dead_proxies: Number(data.counter_proxies.dead_proxies) || 0
          });
        } else {
          setCounters({});
        }
      }
    } catch (err) {
      console.error('Failed to fetch proxy list', err);
      setErrorMsg(String(err));
    } finally {
      setLoading(false);
    }
  }, [page, perPage, search, statusFilter, typeFilter]);

  React.useEffect(() => {
    fetchData().catch(noop);
  }, [fetchData]);

  const handleCopy = async (row: ProxyRow, idx: number) => {
    try {
      let proxyStr = String(row.proxy || '');
      const username = row.username;
      const password = row.password;
      if (username && password && username !== '-' && password !== '-') {
        proxyStr = `${proxyStr}@${username}:${password}`;
      }
      if (!proxyStr) return;
      // Use shared helper when available; fallback to navigator
      if (copyToClipboard) {
        await copyToClipboard(proxyStr);
      } else {
        await navigator.clipboard.writeText(proxyStr);
      }
      setCopiedIndex(idx);
      setTimeout(() => setCopiedIndex((cur) => (cur === idx ? null : cur)), 2000);
    } catch (err) {
      console.error('Copy failed', err);
    }
  };

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

  // Counter items configuration for rendering the summary grid
  const counterList = [
    {
      key: 'total_proxies',
      label: 'Total',
      icon: 'fa-duotone fa-list',
      bg: 'bg-gray-50 dark:bg-gray-800',
      text: 'text-gray-800 dark:text-gray-200'
    },
    {
      key: 'working_proxies',
      label: 'Alive',
      icon: 'fa-duotone fa-heart-pulse',
      bg: 'bg-green-50 dark:bg-green-900',
      text: 'text-green-700 dark:text-green-300'
    },
    {
      key: 'https_proxies',
      label: 'HTTPS',
      icon: 'fa-duotone fa-lock',
      bg: 'bg-blue-50 dark:bg-blue-900',
      text: 'text-blue-700 dark:text-blue-300'
    },
    {
      key: 'private_proxies',
      label: 'Private',
      icon: 'fa-duotone fa-user-secret',
      bg: 'bg-purple-50 dark:bg-purple-900',
      text: 'text-purple-700 dark:text-purple-300'
    },
    {
      key: 'untested_proxies',
      label: 'Untested',
      icon: 'fa-duotone fa-eye-slash',
      bg: 'bg-yellow-50 dark:bg-yellow-900',
      text: 'text-yellow-700 dark:text-yellow-300'
    },
    {
      key: 'dead_proxies',
      label: 'Dead',
      icon: 'fa-duotone fa-skull',
      bg: 'bg-red-50 dark:bg-red-900',
      text: 'text-red-700 dark:text-red-300'
    }
  ];
  return (
    <>
      <section className="my-6">
        <div className="bg-white dark:bg-gray-900 rounded-xl shadow-lg p-4 border border-blue-200 dark:border-blue-700 flowbite-modal">
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-3 gap-3">
            <h2 className="text-lg font-semibold text-blue-800 dark:text-blue-200">{t('proxy_list_server')}</h2>
            <div className="flex flex-col sm:flex-row sm:items-center gap-2 w-full sm:w-auto">
              <input
                type="search"
                placeholder={t('search_proxies') as string}
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
                value={typeFilter}
                onChange={(e) => {
                  setTypeFilter(e.target.value);
                  setPage(1);
                }}
                className="px-2 py-1 border rounded-md text-sm w-full sm:w-auto min-w-[120px]">
                <option value="">All types</option>
                <option value="http">HTTP</option>
                <option value="https">HTTPS</option>
                <option value="socks4">SOCKS4</option>
                <option value="socks4a">SOCKS4A</option>
                <option value="socks5">SOCKS5</option>
                <option value="socks5h">SOCKS5H</option>
                <option value="ssl">SSL</option>
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
                  {t('refresh')}
                </button>
              </div>
            </div>
          </div>

          <div className="relative overflow-x-auto mb-3">
            {errorMsg && (
              <div className="mb-3 p-3 rounded bg-red-50 dark:bg-red-900 text-red-800 dark:text-red-200 border border-red-200 dark:border-red-700 flex justify-between items-start gap-3">
                <div className="text-sm">{errorMsg}</div>
                <button
                  onClick={() => setErrorMsg('')}
                  className="text-sm px-2 py-1 rounded bg-transparent border border-red-200 dark:border-red-700 text-red-700 dark:text-red-200 hover:bg-red-100 dark:hover:bg-red-800">
                  Dismiss
                </button>
              </div>
            )}
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
                      ) : errorMsg ? (
                        <span className="text-red-700 dark:text-red-200">{errorMsg}</span>
                      ) : (
                        <span className="text-gray-700 dark:text-gray-200">No proxies</span>
                      )}
                    </td>
                  </tr>
                ) : (
                  <>
                    {rows.map((r, i) => (
                      <tr key={i} className="odd:bg-gray-50 dark:odd:bg-gray-800">
                        <td className="px-2 py-1 text-gray-900 dark:text-gray-100 whitespace-nowrap">
                          <div className="flex items-center gap-2">
                            <span className="truncate max-w-[18rem] block">{r.proxy}</span>
                            <button
                              type="button"
                              title="Copy proxy"
                              onClick={() => handleCopy(r, i)}
                              className="inline-flex items-center justify-center p-1 rounded bg-gray-200 dark:bg-gray-800 text-gray-800 dark:text-gray-100 hover:bg-gray-300 dark:hover:bg-gray-700 border border-gray-300 dark:border-gray-600">
                              <i className="fa-duotone fa-copy" />
                            </button>
                            {copiedIndex === i && (
                              <span className="text-xs text-green-600 dark:text-green-300">Copied</span>
                            )}
                          </div>
                        </td>
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
                                    'inline-block rounded px-1 py-0.5 mx-0.5 mb-0.5 text-xs font-semibold align-middle border mr-1';
                                  const colorClass = getProxyTypeColorClass(t);
                                  const badgeClass = `${baseClass} ${colorClass}`;
                                  return (
                                    <span key={t + badgeClass} className={badgeClass}>
                                      {t.toUpperCase()}
                                    </span>
                                  );
                                })
                            : '-'}
                          {(r.https === true || String(r.https) === 'true') && (
                            <span
                              className={`inline-block rounded px-1 py-0.5 ml-1 text-xs font-semibold align-middle border ${getProxyTypeColorClass('ssl')}`}>
                              SSL
                            </span>
                          )}
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
                    ))}
                  </>
                )}
              </tbody>
            </table>
            {loading && (
              <div className="absolute inset-0 z-30 flex items-center justify-center bg-white/60 dark:bg-black/60 backdrop-blur-sm pointer-events-auto">
                <div className="px-4 py-3 rounded-lg shadow bg-white dark:bg-gray-900/80 flex items-center gap-3">
                  <i
                    className="fa-duotone fa-spinner fa-spin text-2xl text-gray-700 dark:text-gray-200"
                    aria-hidden="true"
                  />
                  <span className="text-sm text-gray-700 dark:text-gray-200">Loading...</span>
                </div>
              </div>
            )}
          </div>

          <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-3 gap-2">
            <div className="text-sm text-gray-600 dark:text-gray-400">
              <div className="flex flex-col sm:flex-row sm:items-center gap-2">
                <div>{`Showing ${rows.length} of ${recordsFiltered} filtered (${recordsTotal} total)`}</div>
                <div className="flex flex-wrap items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                  {serverDriver ? <span>{`Driver: ${serverDriver}`}</span> : null}
                  {serverDriver ? <span className="mx-1">·</span> : null}
                  <span>{`Page: ${serverPage ?? page}/${totalPages || 1} · Per page: ${serverPerPage ?? perPage}`}</span>
                </div>
              </div>
            </div>
            <div className="flex items-center gap-2 flex-wrap">
              <button
                disabled={page <= 1}
                onClick={() => setPage(1)}
                className="px-2 py-1 border rounded disabled:opacity-50 bg-transparent text-gray-700 dark:text-gray-200 border-gray-300 dark:border-gray-700"
                title="First">
                <i className="fa-duotone fa-angle-double-left" aria-hidden="true" />
                <span className="sr-only">First</span>
              </button>
              <button
                disabled={page <= 1}
                onClick={() => setPage((p) => Math.max(1, p - 1))}
                className="px-2 py-1 border rounded disabled:opacity-50 bg-transparent text-gray-700 dark:text-gray-200 border-gray-300 dark:border-gray-700"
                title="Previous">
                <i className="fa-duotone fa-angle-left" aria-hidden="true" />
                <span className="sr-only">Previous</span>
              </button>

              {/* Numeric page buttons - full on desktop, compact on mobile */}
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

              {/* Compact numeric page buttons for mobile (current ±1) */}
              <div className="flex sm:hidden items-center gap-1">
                {(() => {
                  const pages: number[] = [];
                  const start = Math.max(1, page - 1);
                  const end = Math.min(totalPages, page + 1);
                  for (let p = start; p <= end; p++) pages.push(p);
                  return pages.map((p) => (
                    <button
                      key={p}
                      onClick={() => setPage(p)}
                      className={`px-2 py-1 rounded border text-xs ${p === page ? 'bg-blue-600 text-white' : 'bg-transparent text-gray-700 dark:text-gray-200 border-gray-300 dark:border-gray-700'}`}>
                      {p}
                    </button>
                  ));
                })()}
              </div>

              <button
                disabled={page >= totalPages}
                onClick={() => setPage((p) => Math.min(totalPages, p + 1))}
                className="px-2 py-1 border rounded disabled:opacity-50 bg-transparent text-gray-700 dark:text-gray-200 border-gray-300 dark:border-gray-700"
                title="Next">
                <i className="fa-duotone fa-angle-right" aria-hidden="true" />
                <span className="sr-only">Next</span>
              </button>
              <button
                disabled={page >= totalPages}
                onClick={() => setPage(totalPages)}
                className="px-2 py-1 border rounded disabled:opacity-50 bg-transparent text-gray-700 dark:text-gray-200 border-gray-300 dark:border-gray-700"
                title="Last">
                <i className="fa-duotone fa-angle-double-right" aria-hidden="true" />
                <span className="sr-only">Last</span>
              </button>
            </div>
          </div>

          {/* Proxy counters summary */}
          <div className="mb-3">
            <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-2">
              {counterList.map((item) => (
                <div
                  key={item.key}
                  className={`flex items-center gap-3 px-3 py-2 rounded-lg border ${item.bg} ${item.text} shadow-sm`}>
                  <div className="w-8 h-8 flex items-center justify-center rounded-full bg-white/60 dark:bg-black/40 border border-gray-200 dark:border-gray-700 text-sm">
                    <i className={`${item.icon} text-lg`} aria-hidden="true" />
                  </div>
                  <div className="flex flex-col">
                    <span className="text-xs text-gray-600 dark:text-gray-400">{item.label}</span>
                    <span className="text-sm font-semibold">{(counters as any)[item.key] ?? '-'}</span>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>
      </section>
    </>
  );
}
