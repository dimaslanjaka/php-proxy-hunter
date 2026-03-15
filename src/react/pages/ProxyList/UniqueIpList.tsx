import React from 'react';
import { useTranslation } from 'react-i18next';
import copyToClipboard from '../../../utils/data/copyToClipboard.js';
import { timeAgo } from '../../../utils/date/timeAgo.js';
import { noop } from '../../../utils/other';
import { createUrl } from '../../utils/url';

type UniqueIpRow = {
  ip: string;
  ports: string[];
  proxy_count: number;
  statuses: string[];
  types: string[];
  country: string;
  city: string;
  last_check: string;
  proxy_list: string[];
};

export default function UniqueIpList() {
  const { t } = useTranslation();
  const [rows, setRows] = React.useState<UniqueIpRow[]>([]);
  const [page, setPage] = React.useState(1);
  const [perPage, setPerPage] = React.useState(10);
  // Use a ref for the draw counter so incrementing it never causes a re-render
  // or re-creates the fetchData callback (avoids the infinite-fetch loop).
  const drawRef = React.useRef(0);
  const [recordsTotal, setRecordsTotal] = React.useState(0);
  const [recordsFiltered, setRecordsFiltered] = React.useState(0);
  const [search, setSearch] = React.useState('');
  const [debouncedSearch, setDebouncedSearch] = React.useState('');
  const [countryFilter, setCountryFilter] = React.useState('');
  const [debouncedCountryFilter, setDebouncedCountryFilter] = React.useState('');
  const [cityFilter, setCityFilter] = React.useState('');
  const [debouncedCityFilter, setDebouncedCityFilter] = React.useState('');
  const [statusFilter, setStatusFilter] = React.useState('active');
  const [statuses, setStatuses] = React.useState<string[]>(['active']);
  const [typeFilter, setTypeFilter] = React.useState('');
  const [loading, setLoading] = React.useState(false);
  const [errorMsg, setErrorMsg] = React.useState('');
  const [serverPage, setServerPage] = React.useState<number | null>(null);
  const [serverPerPage, setServerPerPage] = React.useState<number | null>(null);
  const [copiedProxyInfo, setCopiedProxyInfo] = React.useState<{ idx: number; value: string } | null>(null);
  const [copiedListIndex, setCopiedListIndex] = React.useState<number | null>(null);
  const [expandedPorts, setExpandedPorts] = React.useState<Set<number>>(new Set());
  const [selectedPortByIp, setSelectedPortByIp] = React.useState<Record<string, string>>({});

  React.useEffect(() => {
    const timer = setTimeout(() => {
      setDebouncedSearch(search);
    }, 400);
    return () => clearTimeout(timer);
  }, [search]);

  React.useEffect(() => {
    const timer = setTimeout(() => {
      setDebouncedCountryFilter(countryFilter);
    }, 400);
    return () => clearTimeout(timer);
  }, [countryFilter]);

  React.useEffect(() => {
    const timer = setTimeout(() => {
      setDebouncedCityFilter(cityFilter);
    }, 400);
    return () => clearTimeout(timer);
  }, [cityFilter]);

  const togglePorts = (idx: number) =>
    setExpandedPorts((prev) => {
      const next = new Set(prev);
      if (next.has(idx)) {
        next.delete(idx);
      } else {
        next.add(idx);
      }
      return next;
    });

  const fetchData = React.useCallback(async () => {
    setLoading(true);
    try {
      const start = (page - 1) * perPage;
      const body = new URLSearchParams();
      body.append('draw', String(drawRef.current + 1));
      body.append('start', String(start));
      body.append('length', String(perPage));

      if (debouncedSearch) {
        body.append('search[value]', debouncedSearch);
      }
      if (statusFilter) {
        body.append('status', statusFilter);
      }
      if (typeFilter) {
        body.append('type', typeFilter);
      }
      if (debouncedCountryFilter) {
        body.append('country', debouncedCountryFilter);
      }
      if (debouncedCityFilter) {
        body.append('city', debouncedCityFilter);
      }

      const res = await fetch(createUrl('/php_backend/proxy-list-unique-ip.php'), {
        method: 'POST',
        body,
        cache: 'no-store'
      });
      const data = await res.json().catch(() => null);
      if (!res.ok || data?.error) {
        // Prefer the human-readable `message` field the backend may include
        // (e.g. 'Captcha not verified') over a raw error value.
        const msg =
          (data && typeof data.message === 'string' && data.message) ||
          (data && typeof data.error === 'string' && data.error) ||
          `HTTP ${res.status}`;
        setErrorMsg(msg);
        setRows([]);
        setRecordsTotal(0);
        setRecordsFiltered(0);
        return;
      }

      drawRef.current += 1;
      setErrorMsg('');
      setRows(Array.isArray(data.data) ? data.data : []);
      setRecordsTotal(Number(data.recordsTotal) || 0);
      setRecordsFiltered(Number(data.recordsFiltered) || 0);

      const srvPage = Number.isFinite(Number(data.page)) ? Number(data.page) : null;
      const srvPerPage = Number.isFinite(Number(data.perPage)) ? Number(data.perPage) : null;
      setServerPage(srvPage);
      setServerPerPage(srvPerPage);
    } catch (err) {
      setErrorMsg(String(err));
    } finally {
      setLoading(false);
    }
  }, [page, perPage, debouncedSearch, debouncedCountryFilter, debouncedCityFilter, statusFilter, typeFilter]);

  React.useEffect(() => {
    fetchData().catch(noop);
  }, [fetchData]);

  React.useEffect(() => {
    let mounted = true;
    (async () => {
      try {
        const body = new URLSearchParams();
        body.append('get_statuses', '1');
        const res = await fetch(createUrl('/php_backend/proxy-list-unique-ip.php'), { method: 'POST', body });
        if (!res.ok) {
          return;
        }
        const data = await res.json();
        if (!mounted) {
          return;
        }
        if (Array.isArray(data.statuses)) {
          setStatuses(data.statuses.filter(Boolean));
        }
      } catch (_err) {
        // ignore
      }
    })();

    return () => {
      mounted = false;
    };
  }, []);

  const totalPages = perPage > 0 ? Math.max(1, Math.ceil(recordsFiltered / perPage)) : 1;

  const copyProxy = async (value: string, idx: number) => {
    if (!value) {
      return;
    }
    try {
      await copyToClipboard(value);
      setCopiedProxyInfo({ idx, value });
      setTimeout(
        () =>
          setCopiedProxyInfo((cur) => {
            if (cur && cur.idx === idx && cur.value === value) {
              return null;
            }
            return cur;
          }),
        1800
      );
    } catch (_err) {
      // ignore
    }
  };

  const copySelectedPortProxy = async (row: UniqueIpRow, idx: number) => {
    const ip = String(row.ip || '').trim();
    const ports = Array.isArray(row.ports) ? row.ports : [];
    const selected = selectedPortByIp[ip] || ports[0] || '';
    if (!ip || !selected) {
      return;
    }
    await copyProxy(`${ip}:${selected}`, idx);
  };

  const copyProxyList = async (row: UniqueIpRow, idx: number) => {
    const lines = Array.isArray(row.proxy_list) ? row.proxy_list.filter(Boolean) : [];
    if (lines.length === 0) {
      return;
    }
    try {
      await copyToClipboard(lines.join('\n'));
      setCopiedListIndex(idx);
      setTimeout(() => setCopiedListIndex((cur) => (cur === idx ? null : cur)), 1800);
    } catch (_err) {
      // ignore
    }
  };

  return (
    <section className="my-6">
      <div className="relative overflow-hidden rounded-2xl border border-cyan-200/70 dark:border-cyan-900/60 bg-gradient-to-br from-white via-cyan-50/70 to-amber-50/60 dark:from-gray-900 dark:via-cyan-950/40 dark:to-amber-950/30 shadow-xl">
        <div className="absolute -top-16 -right-16 w-56 h-56 rounded-full bg-cyan-300/20 blur-3xl pointer-events-none" />
        <div className="absolute -bottom-20 -left-16 w-56 h-56 rounded-full bg-amber-300/20 blur-3xl pointer-events-none" />

        <div className="relative p-4 sm:p-5">
          <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3 mb-4">
            <div>
              <h2 className="text-lg sm:text-xl font-semibold tracking-tight text-slate-800 dark:text-slate-100">
                Unique IP Proxy List
              </h2>
              <p className="text-xs sm:text-sm text-slate-600 dark:text-slate-300">
                Grouped by IP, with merged ports for each host.
              </p>
            </div>

            <div className="flex flex-wrap items-center gap-2">
              <button
                title={t('refresh') as string}
                onClick={() => fetchData().catch(noop)}
                className="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-cyan-700 text-white text-sm hover:bg-cyan-600">
                <i className="fa-duotone fa-arrows-rotate" aria-hidden="true" />
                <span>Refresh</span>
              </button>
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-6 gap-2 mb-4">
            <input
              type="search"
              value={search}
              onChange={(e) => {
                setSearch(e.target.value);
                setPage(1);
              }}
              placeholder={t('search_proxies') as string}
              className="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white/90 dark:bg-slate-900/70 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 dark:placeholder:text-slate-500"
            />

            <input
              type="search"
              value={countryFilter}
              onChange={(e) => {
                setCountryFilter(e.target.value);
                setPage(1);
              }}
              placeholder="Country"
              className="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white/90 dark:bg-slate-900/70 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 dark:placeholder:text-slate-500"
            />

            <input
              type="search"
              value={cityFilter}
              onChange={(e) => {
                setCityFilter(e.target.value);
                setPage(1);
              }}
              placeholder="City"
              className="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white/90 dark:bg-slate-900/70 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 dark:placeholder:text-slate-500"
            />

            <select
              value={statusFilter}
              onChange={(e) => {
                setStatusFilter(e.target.value);
                setPage(1);
              }}
              className="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white/90 dark:bg-slate-900/70 text-sm text-slate-900 dark:text-slate-100 [&>option]:bg-white [&>option]:dark:bg-slate-900">
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
              className="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white/90 dark:bg-slate-900/70 text-sm text-slate-900 dark:text-slate-100 [&>option]:bg-white [&>option]:dark:bg-slate-900">
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
              className="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white/90 dark:bg-slate-900/70 text-sm text-slate-900 dark:text-slate-100 [&>option]:bg-white [&>option]:dark:bg-slate-900">
              {[10, 25, 50, 100].map((n) => (
                <option key={n} value={n}>
                  {n}
                </option>
              ))}
            </select>
          </div>

          {errorMsg ? (
            <div className="mb-3 px-3 py-2 rounded-lg border border-rose-200 dark:border-rose-800 bg-rose-50 dark:bg-rose-900/30 text-rose-700 dark:text-rose-200 text-sm">
              {errorMsg}
            </div>
          ) : null}

          <div className="relative overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-800 bg-white/80 dark:bg-gray-900/50">
            <table className="w-full text-sm text-left">
              <thead className="text-slate-700 dark:text-slate-200">
                <tr className="border-b border-slate-200 dark:border-slate-800">
                  <th className="px-3 py-2 whitespace-nowrap">IP</th>
                  <th className="px-3 py-2 whitespace-nowrap">Ports</th>
                  <th className="px-3 py-2 whitespace-nowrap">Types</th>
                  <th className="px-3 py-2 whitespace-nowrap">Location</th>
                  <th className="px-3 py-2 whitespace-nowrap">Last Check</th>
                  <th className="px-3 py-2 whitespace-nowrap">Actions</th>
                </tr>
              </thead>
              <tbody>
                {rows.length === 0 ? (
                  <tr>
                    <td colSpan={6} className="px-3 py-8 text-center text-slate-500 dark:text-slate-400">
                      {loading ? 'Loading...' : 'No unique IP proxies found'}
                    </td>
                  </tr>
                ) : (
                  rows.map((row, idx) => (
                    <tr
                      key={`${row.ip}-${idx}`}
                      className={`border-b last:border-0 ${(() => {
                        const statuses = (Array.isArray(row.statuses) ? row.statuses : []).map((s) =>
                          String(s || '').toLowerCase()
                        );
                        if (statuses.includes('active')) {
                          return 'border-emerald-200 dark:border-emerald-900/70';
                        }
                        if (statuses.includes('untested') || statuses.includes('port-open')) {
                          return 'border-amber-200 dark:border-amber-900/70';
                        }
                        return 'border-rose-200 dark:border-rose-900/70';
                      })()}`}>
                      <td className="px-3 py-2 whitespace-nowrap font-medium text-slate-900 dark:text-slate-100">
                        <div className="inline-flex items-center gap-1.5">
                          <span>{row.ip}</span>
                          {Number(row.proxy_count) > 1 ? (
                            <span className="inline-flex items-center rounded-full px-1.5 py-0.5 text-[11px] border border-cyan-300 bg-cyan-50 text-cyan-700 dark:border-cyan-700 dark:bg-cyan-900/40 dark:text-cyan-200">
                              {row.proxy_count}
                            </span>
                          ) : null}
                        </div>
                      </td>
                      <td className="px-3 py-2">
                        <div className="flex flex-wrap gap-1 max-w-[22rem]">
                          {(() => {
                            const ports = Array.isArray(row.ports) ? row.ports : [];
                            const LIMIT = 5;
                            const isExpanded = expandedPorts.has(idx);
                            const visible = isExpanded ? ports : ports.slice(0, LIMIT);
                            const hidden = ports.length - LIMIT;
                            return (
                              <>
                                {visible.map((p) => (
                                  <span
                                    key={`${row.ip}-${p}`}
                                    className="inline-block px-2 py-0.5 text-xs rounded-full border border-cyan-200 bg-cyan-50 text-cyan-700 dark:border-cyan-800 dark:bg-cyan-900/40 dark:text-cyan-200">
                                    {p}
                                  </span>
                                ))}
                                {ports.length > LIMIT && (
                                  <button
                                    type="button"
                                    onClick={() => togglePorts(idx)}
                                    className="inline-block px-2 py-0.5 text-xs rounded-full border border-slate-300 dark:border-slate-600 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700">
                                    {isExpanded ? '− less' : `+${hidden} more`}
                                  </button>
                                )}
                              </>
                            );
                          })()}
                        </div>
                      </td>
                      <td className="px-3 py-2">
                        <div className="flex flex-wrap gap-1 max-w-[14rem]">
                          {(Array.isArray(row.types) ? row.types : []).map((tp) => (
                            <span
                              key={`${row.ip}-type-${tp}`}
                              className="inline-block px-2 py-0.5 text-xs rounded border border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-800 dark:bg-amber-900/40 dark:text-amber-200">
                              {String(tp).toUpperCase()}
                            </span>
                          ))}
                        </div>
                      </td>
                      <td className="px-3 py-2 whitespace-nowrap text-slate-700 dark:text-slate-300">
                        {(() => {
                          const normalizeLocationPart = (value: unknown) => {
                            const v = String(value || '').trim();
                            if (!v || v === '-' || v.toUpperCase() === 'N/A') {
                              return '';
                            }
                            return v;
                          };

                          const country = normalizeLocationPart(row.country);
                          const city = normalizeLocationPart(row.city);
                          if (country && city) return `${country} / ${city}`;
                          if (country) return country;
                          if (city) return city;
                          return '-';
                        })()}
                      </td>
                      <td className="px-3 py-2 whitespace-nowrap text-slate-700 dark:text-slate-300">
                        {row.last_check && row.last_check !== '-' ? timeAgo(row.last_check) : '-'}
                      </td>
                      <td className="px-3 py-2 whitespace-nowrap">
                        <div className="flex items-center gap-1 flex-wrap">
                          {(() => {
                            const ip = String(row.ip || '').trim();
                            const ports = Array.isArray(row.ports) ? row.ports : [];
                            const hasMultiplePorts = ports.length > 1;
                            const selected = selectedPortByIp[ip] || ports[0] || '';

                            if (hasMultiplePorts) {
                              return (
                                <>
                                  <select
                                    value={selected}
                                    onChange={(e) =>
                                      setSelectedPortByIp((prev) => ({
                                        ...prev,
                                        [ip]: e.target.value
                                      }))
                                    }
                                    className="px-2 py-1 text-xs rounded border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 min-w-[84px]">
                                    {ports.map((p) => (
                                      <option key={`${ip}-copy-${p}`} value={p}>
                                        {p}
                                      </option>
                                    ))}
                                  </select>
                                  <button
                                    type="button"
                                    onClick={() => copySelectedPortProxy(row, idx)}
                                    className="inline-flex items-center gap-1 px-2 py-1 text-xs rounded border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800">
                                    <i className="fa-duotone fa-copy" aria-hidden="true" />
                                    <span>Copy</span>
                                  </button>
                                </>
                              );
                            }

                            return (
                              <button
                                type="button"
                                onClick={() => copyProxy(row.proxy_list?.[0] || ip, idx)}
                                className="inline-flex items-center gap-1 px-2 py-1 text-xs rounded border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800">
                                <i className="fa-duotone fa-copy" aria-hidden="true" />
                                <span>Copy</span>
                              </button>
                            );
                          })()}
                          {(Array.isArray(row.ports) ? row.ports.length : 0) > 1 ? (
                            <button
                              type="button"
                              onClick={() => copyProxyList(row, idx)}
                              className="inline-flex items-center gap-1 px-2 py-1 text-xs rounded border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800">
                              <i className="fa-duotone fa-list" aria-hidden="true" />
                              <span>All</span>
                            </button>
                          ) : null}
                          {copiedProxyInfo?.idx === idx ? (
                            <span className="text-[11px] text-emerald-600">{`${copiedProxyInfo.value} copied`}</span>
                          ) : null}
                          {copiedListIndex === idx ? (
                            <span className="text-[11px] text-cyan-600">List copied</span>
                          ) : null}
                        </div>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>

            {loading ? (
              <div className="absolute inset-0 flex items-center justify-center bg-white/60 dark:bg-black/60 backdrop-blur-sm">
                <div className="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-white dark:bg-gray-900 border border-slate-200 dark:border-slate-700 text-sm text-slate-700 dark:text-slate-200">
                  <i className="fa-duotone fa-spinner fa-spin" aria-hidden="true" />
                  <span>Loading...</span>
                </div>
              </div>
            ) : null}
          </div>

          <div className="mt-3 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 text-sm text-slate-600 dark:text-slate-400">
            <div className="flex flex-col gap-0.5">
              <span>{`Showing ${rows.length} of ${recordsFiltered} unique IPs (${recordsTotal} total)`}</span>
              <span className="text-xs">{`Page: ${serverPage ?? page}/${totalPages} · Per page: ${serverPerPage ?? perPage}`}</span>
            </div>

            <div className="flex items-center gap-1 flex-wrap">
              <button
                disabled={page <= 1}
                onClick={() => setPage(1)}
                className="px-2 py-1 rounded border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 disabled:opacity-50">
                <i className="fa-duotone fa-angle-double-left" aria-hidden="true" />
              </button>
              <button
                disabled={page <= 1}
                onClick={() => setPage((p) => Math.max(1, p - 1))}
                className="px-2 py-1 rounded border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 disabled:opacity-50">
                <i className="fa-duotone fa-angle-left" aria-hidden="true" />
              </button>

              {(() => {
                const maxButtons = 5;
                const pages: number[] = [];
                let start = Math.max(1, page - Math.floor(maxButtons / 2));
                let end = start + maxButtons - 1;
                if (end > totalPages) {
                  end = totalPages;
                  start = Math.max(1, end - maxButtons + 1);
                }
                for (let p = start; p <= end; p++) {
                  pages.push(p);
                }
                return pages.map((p) => (
                  <button
                    key={p}
                    onClick={() => setPage(p)}
                    className={`px-2 py-1 rounded border ${
                      p === page
                        ? 'bg-cyan-700 text-white border-cyan-700'
                        : 'border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200'
                    }`}>
                    {p}
                  </button>
                ));
              })()}

              <button
                disabled={page >= totalPages}
                onClick={() => setPage((p) => Math.min(totalPages, p + 1))}
                className="px-2 py-1 rounded border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 disabled:opacity-50">
                <i className="fa-duotone fa-angle-right" aria-hidden="true" />
              </button>
              <button
                disabled={page >= totalPages}
                onClick={() => setPage(totalPages)}
                className="px-2 py-1 rounded border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 disabled:opacity-50">
                <i className="fa-duotone fa-angle-double-right" aria-hidden="true" />
              </button>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}
