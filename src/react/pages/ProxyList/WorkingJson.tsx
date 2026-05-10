import React from 'react';
import { useTranslation } from 'react-i18next';
import { ProxyDetails } from '../../../../types/proxy';
import { add_ajax_schedule, run_ajax_schedule } from '../../../utils/ajaxScheduler';
import copyToClipboard from '../../../utils/data/copyToClipboard.js';
import { timeAgo } from '../../../utils/date/timeAgo.js';
import { noop } from '../../../utils/other';
import { useSnackbar } from '../../components/Snackbar';
import { checkProxy } from '../../utils/proxy';
import { getProxyTypeColorClass } from '../../utils/proxyColors';
import { createUrl } from '../../utils/url';
import { getUserInfo } from '../../utils/user';

/**
 * Handler to re-check a proxy (calls backend API, supports user/pass)
 * @param proxy ProxyDetails object
 */
const handleRecheck = async (
  proxy: ProxyDetails,
  showSnackbar: (options: { message: string; type: 'success' | 'danger' }) => void
) => {
  try {
    const data = await checkProxy(proxy.proxy || '');
    if (data.error) {
      showSnackbar({ message: data.message || 'Failed to re-check proxy.', type: 'danger' });
    } else {
      showSnackbar({ message: data.message || 'Proxy re-check initiated.', type: 'success' });
    }

    // Optionally, you could refresh the proxy list here
    // fetchAndSetProxies(setProxies);
  } catch (err) {
    showSnackbar({ message: 'Failed to re-check proxy.', type: 'danger' });
    console.error(err);
  }
};

// Handler to copy proxy string to clipboard (includes credentials when present)
const handleCopy = async (proxy: ProxyDetails, showSnackbar?: any) => {
  if (!proxy || !proxy.proxy) return;
  let proxyStr = String(proxy.proxy || '');
  if (proxy.username && proxy.password && proxy.username !== '-' && proxy.password !== '-') {
    proxyStr = `${proxyStr}@${proxy.username}:${proxy.password}`;
  }
  try {
    // copyToClipboard may be a Promise or boolean-returning function
    const res = copyToClipboard ? await copyToClipboard(proxyStr) : null;
    if (showSnackbar && typeof showSnackbar === 'function') {
      showSnackbar({ message: 'Copied', type: 'success' });
    }
    return res;
  } catch (err) {
    if (showSnackbar && typeof showSnackbar === 'function') {
      showSnackbar({ message: 'Copy failed', type: 'danger' });
    }
    console.error('Copy failed', err);
    return false;
  }
};

const normalizeTun2SocksValue = (value: unknown): number | null => {
  const raw = String(value ?? '').trim();
  if (!raw || raw === '-' || raw.toLowerCase() === 'n/a') {
    return null;
  }

  const parsed = Number(raw);
  if (!Number.isFinite(parsed)) {
    return null;
  }

  return Math.max(0, Math.min(parsed, 100));
};

const normalizeLatencyValue = (value: unknown): number | null => {
  const raw = String(value ?? '').trim();
  if (!raw || raw === '-' || raw.toLowerCase() === 'n/a') {
    return null;
  }

  const parsed = Number(raw);
  if (!Number.isFinite(parsed) || parsed <= 0) {
    return null;
  }

  return parsed;
};

// getWorkingProxies moved inside the component for easier access to state and refs

// Module-level cache to avoid long waits between navigations
let workingProxiesCache: any[] | null = null;

// Helper to fetch proxies (kept at module level)
// (fetchAndSetProxies moved into the component to use setProxies from closure)

function WorkingJson() {
  const { t } = useTranslation();
  const { showSnackbar } = useSnackbar();
  // ref to avoid re-entrant fetches
  const fetchingProxiesRef = React.useRef(false);
  const [typeFilter, setTypeFilter] = React.useState('');
  const [proxies, setProxies] = React.useState<ProxyDetails[]>([]);

  const [page, setPage] = React.useState(1);
  const [rowsPerPage, setRowsPerPage] = React.useState(10);
  const [countryFilter, setCountryFilter] = React.useState('');
  const [cityFilter, setCityFilter] = React.useState('');
  const [timezoneFilter, setTimezoneFilter] = React.useState('');
  const [regionFilter, setRegionFilter] = React.useState('');
  const [searchQuery, setSearchQuery] = React.useState('');
  const [userId, setUserId] = React.useState<string | null>(null);
  // filters always shown (accordion removed)
  const [loadingProxies, setLoadingProxies] = React.useState(false);
  const isMountedRef = React.useRef(true);

  // Helper to fetch and set proxies using component's setProxies
  const fetchAndSetProxies = async () => {
    const result = await getWorkingProxies();
    // Only replace caches when we have a valid non-empty array result.
    // This prevents clearing the UI when embed.php returned invalid JSON or failed to parse.
    if (Array.isArray(result) && result.length > 0) {
      setProxies(result);
      workingProxiesCache = result;
      try {
        localStorage.setItem('workingProxiesCache', JSON.stringify(result));
      } catch {
        // ignore storage errors
      }
    } else {
      // If fetch failed or returned invalid data, try to reuse existing caches
      if (workingProxiesCache && Array.isArray(workingProxiesCache) && workingProxiesCache.length > 0) {
        setProxies(workingProxiesCache);
      } else {
        try {
          const cached = localStorage.getItem('workingProxiesCache');
          if (cached) {
            const parsed = JSON.parse(cached);
            if (Array.isArray(parsed) && parsed.length > 0) setProxies(parsed);
          }
        } catch {
          // ignore parse/storage errors
        }
      }
    }
  };

  // Fetch working proxies (scoped inside component) to use refs/state easily
  const getWorkingProxies = React.useCallback(async () => {
    if (fetchingProxiesRef.current) return [];
    fetchingProxiesRef.current = true;
    // refresh working.json in background (non-blocking, no need to await, and ignore errors)
    fetch(createUrl('/artisan/proxyWorking.php')).catch(noop);
    let result: any = [];
    try {
      const res = await fetch(createUrl('/embed.php?file=working.json&hash=' + Date.now()), {
        signal: AbortSignal.timeout(5000)
      });
      if (res.status === 403) {
        fetchingProxiesRef.current = false;
        return [];
      }
      result = await res.json();
      if (result.error) {
        fetchingProxiesRef.current = false;
        return [];
      }
      if (!Array.isArray(result)) {
        fetchingProxiesRef.current = false;
        return [];
      }
    } catch {
      fetchingProxiesRef.current = false;
      return [];
    }
    fetchingProxiesRef.current = false;
    for (let i = 0; i < result.length; i++) {
      const proxy = result[i];
      if (proxy.https === 'true') {
        proxy.type = proxy.type ? `${proxy.type}-SSL` : 'SSL';
      }
      delete (proxy as Partial<ProxyDetails>).https;
      result[i] = proxy;
    }
    // Sort by last_check (date string), most recent first
    result.sort((a, b) => {
      const dateA = a.last_check ? new Date(a.last_check).getTime() : 0;
      const dateB = b.last_check ? new Date(b.last_check).getTime() : 0;
      return dateB - dateA;
    });
    return result;
  }, []);

  // Get ProxyDetails keys for table, reordering specific columns to the end
  const proxyKeys = React.useMemo(() => {
    if (!proxies[0]) return [];
    const keys = Object.keys(proxies[0]);
    const lastCols = ['useragent', 'webgl_vendor', 'browser_vendor', 'webgl_renderer'];
    const main = keys.filter((k) => !lastCols.includes(k));
    const last = lastCols.filter((k) => keys.includes(k));
    return [...main, ...last].filter((key) => key !== 'tun2socks');
  }, [proxies]);

  // Unique values for dropdown filters
  const uniqueCountries = React.useMemo(
    () => Array.from(new Set(proxies.map((p) => p.country).filter(Boolean))).sort(),
    [proxies]
  );
  const uniqueCities = React.useMemo(
    () => Array.from(new Set(proxies.map((p) => p.city).filter(Boolean))).sort(),
    [proxies]
  );
  const uniqueTimezones = React.useMemo(
    () => Array.from(new Set(proxies.map((p) => p.timezone).filter(Boolean))).sort(),
    [proxies]
  );
  const uniqueRegions = React.useMemo(
    () => Array.from(new Set(proxies.map((p) => p.region).filter(Boolean))).sort(),
    [proxies]
  );

  // Filtered and paginated proxies
  const filteredProxies = React.useMemo(() => {
    let filtered = proxies;
    if (countryFilter) {
      filtered = filtered.filter((p) => String(p.country || '') === countryFilter);
    }
    if (cityFilter) {
      filtered = filtered.filter((p) => String(p.city || '') === cityFilter);
    }
    if (timezoneFilter) {
      filtered = filtered.filter((p) => String(p.timezone || '') === timezoneFilter);
    }
    if (regionFilter) {
      filtered = filtered.filter((p) => String(p.region || '') === regionFilter);
    }
    if (typeFilter) {
      filtered = filtered.filter((p) => {
        const typeStr = String(p.type || '').toLowerCase();
        if (typeFilter === 'ssl') {
          return typeStr.includes('ssl');
        }
        return typeStr.split('-').includes(typeFilter);
      });
    }
    // apply search query filtering across all string fields
    if (searchQuery && String(searchQuery).trim().length > 0) {
      const q = String(searchQuery).toLowerCase().trim();
      filtered = filtered.filter((p) =>
        Object.values(p).some((v) =>
          String(v ?? '')
            .toLowerCase()
            .includes(q)
        )
      );
    }
    return filtered;
  }, [proxies, countryFilter, cityFilter, timezoneFilter, typeFilter, regionFilter, searchQuery]);

  const paginatedProxies = React.useMemo(() => {
    const start = (page - 1) * rowsPerPage;
    return filteredProxies.slice(start, start + rowsPerPage);
  }, [filteredProxies, page, rowsPerPage]);

  const totalPages = Math.ceil(filteredProxies.length / rowsPerPage) || 1;

  // On mount
  React.useEffect(() => {
    // mounted flag for safe updates
    isMountedRef.current = true;

    // Helper to refresh proxies with loading state
    const refreshProxies = async () => {
      setLoadingProxies(true);
      try {
        await fetchAndSetProxies();
      } finally {
        if (isMountedRef.current) setLoadingProxies(false);
      }
    };

    // initial load: first try module cache, then localStorage cache, then fetch
    (async () => {
      if (workingProxiesCache && Array.isArray(workingProxiesCache) && workingProxiesCache.length > 0) {
        setProxies(workingProxiesCache);
      } else {
        try {
          const cached = localStorage.getItem('workingProxiesCache');
          if (cached) {
            const parsed = JSON.parse(cached);
            if (Array.isArray(parsed) && parsed.length > 0) setProxies(parsed);
          }
        } catch {
          // ignore parse errors
        }
      }
      // always refresh in background to get latest list
      await refreshProxies();
    })();

    return () => {
      isMountedRef.current = false;
    };
  }, [setProxies]);

  // Fetch user info once and store userId
  React.useEffect(() => {
    let mounted = true;
    (async () => {
      try {
        const info = await getUserInfo();
        if (!mounted) return;
        const uid = (info as any)?.uid || (info as any)?.user_id || null;
        if (uid && mounted) setUserId(uid);
      } catch (_err) {
        // ignore
      }
    })();
    return () => {
      mounted = false;
    };
  }, []);

  // Auto-refresh removed: manual refresh is available via the Refresh button

  // Manual refresh handler exposed to UI
  const handleRefresh = async () => {
    if (loadingProxies) return;
    setLoadingProxies(true);
    try {
      await fetchAndSetProxies();
    } finally {
      if (isMountedRef.current) setLoadingProxies(false);
    }
  };

  // Enqueue proxies with missing timezone to the local AJAX scheduler
  const candidatesToUpdate = React.useMemo(() => {
    if (!proxies || proxies.length === 0) return [];

    return (
      proxies
        .filter((p) => {
          const tz = p.timezone;
          return !tz || tz.trim() === '' || tz === 'N/A' || tz === '-';
        })
        // randomize but avoid expensive sort; pick 5 random indices
        .sort(() => Math.random() - 0.5)
        .slice(0, 5)
    );
  }, [proxies]);

  React.useEffect(() => {
    if (!userId) return;
    if (candidatesToUpdate.length === 0) return;

    // Collect all candidates with credentials in IP:PORT@USER:PASS format
    const proxyData = candidatesToUpdate.map((p) => {
      let proxyStr = p.proxy || '';
      // Add credentials only if both username and password exist and are not "-"
      if (p.username && p.password && p.username !== '-' && p.password !== '-') {
        proxyStr = `${proxyStr}@${p.username}:${p.password}`;
      }
      return proxyStr;
    });
    add_ajax_schedule(createUrl('/php_backend/executor.php?file=/artisan/geoIp.py'), {
      method: 'POST_JSON',
      data: { uid: userId, proxy: JSON.stringify(proxyData) }
    });

    run_ajax_schedule();
  }, [userId, candidatesToUpdate]);

  // Enqueue proxies with missing latency to the local AJAX scheduler
  const candidatesLatencyUpdate = React.useMemo(() => {
    if (!proxies || proxies.length === 0) return [];

    return (
      proxies
        .filter((p) => {
          const latency = p.latency;
          return (
            !latency ||
            String(latency).trim() === '' ||
            String(latency) === '0' ||
            String(latency) === 'N/A' ||
            String(latency) === '-'
          );
        })
        // randomize but avoid expensive sort; pick 5 random indices
        .sort(() => Math.random() - 0.5)
        .slice(0, 5)
    );
  }, [proxies]);

  React.useEffect(() => {
    if (!userId) return;
    if (candidatesLatencyUpdate.length === 0) return;

    // Collect all candidates with credentials in IP:PORT@USER:PASS format
    const proxyData = candidatesLatencyUpdate.map((p) => {
      let proxyStr = p.proxy || '';
      // Add credentials only if both username and password exist and are not "-"
      if (p.username && p.password && p.username !== '-' && p.password !== '-') {
        proxyStr = `${proxyStr}@${p.username}:${p.password}`;
      }
      return proxyStr;
    });
    add_ajax_schedule(createUrl('/php_backend/check-latency-proxy.php'), {
      method: 'POST_JSON',
      data: { uid: userId, proxy: JSON.stringify(proxyData) }
    });

    run_ajax_schedule();
  }, [userId, candidatesLatencyUpdate]);

  return (
    <section className="my-6">
      <div className="relative overflow-hidden rounded-2xl border border-teal-200/70 dark:border-teal-900/60 bg-gradient-to-br from-white via-teal-50/70 to-violet-50/60 dark:from-gray-900 dark:via-teal-950/40 dark:to-violet-950/30 shadow-xl">
        <div className="absolute -top-16 -right-16 w-56 h-56 rounded-full bg-teal-300/20 blur-3xl pointer-events-none" />
        <div className="absolute -bottom-20 -left-16 w-56 h-56 rounded-full bg-violet-300/20 blur-3xl pointer-events-none" />

        <div className="relative p-4 sm:p-5">
          <div className="flex items-center justify-between mb-4">
            <h1 className="text-2xl font-bold text-slate-900 dark:text-slate-100 flex items-center gap-2">
              <i className="fa-duotone fa-list-check"></i> Proxy List
            </h1>
            <button
              className="ml-0 sm:ml-4 px-3 py-1 bg-teal-600 hover:bg-teal-500 text-white text-xs font-semibold rounded shadow transition-colors flex items-center gap-1 flex-shrink-0"
              onClick={handleRefresh}
              disabled={loadingProxies}
              title={'Refresh proxies'}
              aria-label={'Refresh proxies'}>
              <i className="fa fa-refresh sm:hidden" aria-hidden="true"></i>
              <span className="hidden sm:inline">Refresh</span>
            </button>
          </div>
          {/* Filter controls (always visible) */}
          <div className="mb-4 w-full">
            <div className="block">
              <div className="flex flex-col sm:flex-row flex-wrap gap-x-4 gap-y-2 mt-4 items-center w-full">
                <div className="flex flex-col sm:flex-1 w-full sm:w-auto min-w-[200px]">
                  <label className="text-slate-700 dark:text-slate-200 mb-1">Search:</label>
                  <input
                    type="search"
                    value={searchQuery}
                    onChange={(e) => {
                      setSearchQuery(e.target.value);
                      setPage(1);
                    }}
                    placeholder="Search proxies..."
                    className="border rounded px-2 py-1 border-slate-300 dark:border-slate-700 bg-white/90 dark:bg-slate-900/70 text-slate-900 dark:text-slate-100 w-full"
                  />
                </div>

                <div className="flex flex-col sm:flex-1 w-full sm:w-auto min-w-[150px]">
                  <label className="text-slate-700 dark:text-slate-200 mb-1">Type:</label>
                  <select
                    value={typeFilter}
                    onChange={(e) => {
                      setTypeFilter(e.target.value);
                      setPage(1);
                    }}
                    className="border rounded px-2 py-1 border-slate-300 dark:border-slate-700 bg-white/90 dark:bg-slate-900/70 text-slate-900 dark:text-slate-100 w-full [&>option]:bg-white [&>option]:dark:bg-slate-900">
                    <option value="">All Types</option>
                    <option value="http">HTTP</option>
                    <option value="socks4">SOCKS4</option>
                    <option value="socks5">SOCKS5</option>
                    <option value="ssl">SSL</option>
                  </select>
                </div>
                <div className="flex flex-col sm:flex-1 w-full sm:w-auto min-w-[150px]">
                  <label className="text-slate-700 dark:text-slate-200 mb-1">Country:</label>
                  <select
                    value={countryFilter}
                    onChange={(e) => {
                      setCountryFilter(e.target.value);
                      setPage(1);
                    }}
                    className="border rounded px-2 py-1 border-slate-300 dark:border-slate-700 bg-white/90 dark:bg-slate-900/70 text-slate-900 dark:text-slate-100 w-full [&>option]:bg-white [&>option]:dark:bg-slate-900">
                    <option value="">All Countries</option>
                    {uniqueCountries.map((country) => (
                      <option key={country} value={country}>
                        {country}
                      </option>
                    ))}
                  </select>
                </div>
                <div className="flex flex-col sm:flex-1 w-full sm:w-auto min-w-[150px]">
                  <label className="text-slate-700 dark:text-slate-200 mb-1">City:</label>
                  <select
                    value={cityFilter}
                    onChange={(e) => {
                      setCityFilter(e.target.value);
                      setPage(1);
                    }}
                    className="border rounded px-2 py-1 border-slate-300 dark:border-slate-700 bg-white/90 dark:bg-slate-900/70 text-slate-900 dark:text-slate-100 w-full [&>option]:bg-white [&>option]:dark:bg-slate-900">
                    <option value="">All Cities</option>
                    {uniqueCities.map((city) => (
                      <option key={city} value={city}>
                        {city}
                      </option>
                    ))}
                  </select>
                </div>
                <div className="flex flex-col sm:flex-1 w-full sm:w-auto min-w-[150px]">
                  <label className="text-slate-700 dark:text-slate-200 mb-1">Region:</label>
                  <select
                    value={regionFilter}
                    onChange={(e) => {
                      setRegionFilter(e.target.value);
                      setPage(1);
                    }}
                    className="border rounded px-2 py-1 border-slate-300 dark:border-slate-700 bg-white/90 dark:bg-slate-900/70 text-slate-900 dark:text-slate-100 w-full [&>option]:bg-white [&>option]:dark:bg-slate-900">
                    <option value="">All Regions</option>
                    {uniqueRegions.map((r) => (
                      <option key={r} value={r}>
                        {r}
                      </option>
                    ))}
                  </select>
                </div>
                <div className="flex flex-col sm:flex-1 w-full sm:w-auto min-w-[150px]">
                  <label className="text-slate-700 dark:text-slate-200 mb-1">Timezone:</label>
                  <select
                    value={timezoneFilter}
                    onChange={(e) => {
                      setTimezoneFilter(e.target.value);
                      setPage(1);
                    }}
                    className="border rounded px-2 py-1 border-slate-300 dark:border-slate-700 bg-white/90 dark:bg-slate-900/70 text-slate-900 dark:text-slate-100 w-full [&>option]:bg-white [&>option]:dark:bg-slate-900">
                    <option value="">All Timezones</option>
                    {uniqueTimezones.map((tz) => (
                      <option key={tz} value={tz}>
                        {tz}
                      </option>
                    ))}
                  </select>
                </div>
              </div>
            </div>
          </div>
          {/* Table */}
          <div className="overflow-x-auto rounded-lg shadow border border-slate-200 dark:border-slate-800 bg-white/80 dark:bg-gray-900/50">
            <table className="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
              <thead className="bg-slate-100/90 dark:bg-slate-800/80">
                <tr>
                  <th className="px-2 py-1 text-xs font-semibold text-slate-700 dark:text-slate-200 uppercase tracking-wider border-b border-slate-200 dark:border-slate-700 text-center">
                    Actions
                  </th>
                  {proxyKeys.map((key) => (
                    <th
                      key={key}
                      className="px-2 py-1 text-xs font-semibold text-slate-700 dark:text-slate-200 uppercase tracking-wider border-b border-slate-200 dark:border-slate-700">
                      {key}
                    </th>
                  ))}
                  <th className="px-2 py-1 text-xs font-semibold text-slate-700 dark:text-slate-200 uppercase tracking-wider border-b border-slate-200 dark:border-slate-700 whitespace-nowrap">
                    Tun2Socks
                  </th>
                </tr>
              </thead>
              <tbody className="bg-white/85 dark:bg-gray-900/60 divide-y divide-slate-100 dark:divide-slate-800">
                {paginatedProxies.map((proxy, idx) => {
                  const tun2socksValue = normalizeTun2SocksValue(proxy.tun2socks);

                  return (
                    <tr key={idx} className="hover:bg-slate-50/80 dark:hover:bg-slate-800/70">
                      <td className="px-2 py-1 text-xs whitespace-nowrap border-b border-slate-100 dark:border-slate-800 text-center">
                        <button
                          title={t('recheck_proxy')}
                          className="inline-flex items-center justify-center p-1 rounded bg-amber-100 dark:bg-amber-700 text-amber-800 dark:text-amber-200 hover:bg-amber-200 dark:hover:bg-amber-600 mr-1"
                          onClick={() => handleRecheck(proxy, showSnackbar)}>
                          <i className="fa-duotone fa-rotate-right"></i>
                        </button>
                        <button
                          title={t('copy_proxy')}
                          className="inline-flex items-center justify-center p-1 rounded bg-slate-200 dark:bg-slate-800 text-slate-800 dark:text-slate-100 hover:bg-slate-300 dark:hover:bg-slate-700 border border-slate-300 dark:border-slate-600"
                          onClick={() => handleCopy(proxy)}>
                          <i className="fa-duotone fa-copy"></i>
                        </button>
                      </td>
                      {proxyKeys.map((key) => (
                        <td
                          key={key}
                          className="px-2 py-1 text-xs whitespace-nowrap border-b border-slate-100 dark:border-slate-800 text-slate-900 dark:text-slate-100">
                          {key === 'last_check' && proxy[key]
                            ? timeAgo(proxy[key], true, true)
                            : key === 'type' && proxy[key]
                              ? String(proxy[key])
                                  .split('-')
                                  .filter(Boolean)
                                  .map((t) => {
                                    const baseClass =
                                      'inline-block rounded px-2 py-0.5 mx-0.5 mb-0.5 text-xxs font-semibold align-middle border mr-1';
                                    const colorClass = getProxyTypeColorClass(t);
                                    const badgeClass = `${baseClass} ${colorClass}`;

                                    return (
                                      <span key={t + 'type' + badgeClass} className={badgeClass}>
                                        {t.toUpperCase()}
                                      </span>
                                    );
                                  })
                              : key === 'latency'
                                ? normalizeLatencyValue(proxy[key]) !== null
                                  ? `${normalizeLatencyValue(proxy[key])} ms`
                                  : '-'
                                : String(proxy[key] ?? '')}
                        </td>
                      ))}
                      <td className="px-2 py-1 text-slate-900 dark:text-slate-100 whitespace-nowrap border-b border-slate-100 dark:border-slate-800">
                        {tun2socksValue !== null ? (
                          <div className="flex items-center">
                            <div className="relative w-20 h-5 bg-slate-300 dark:bg-slate-700 rounded-full overflow-hidden border border-slate-400 dark:border-slate-600">
                              <div
                                className="absolute inset-y-0 left-0 bg-gradient-to-r from-teal-500 to-violet-500 dark:from-teal-600 dark:to-violet-600 transition-all duration-300"
                                style={{ width: `${tun2socksValue}%` }}
                              />
                              <div
                                className={`absolute inset-0 flex items-center justify-center text-[11px] font-bold ${
                                  tun2socksValue >= 45 ? 'text-white' : 'text-slate-700 dark:text-slate-100'
                                }`}>
                                {`${Math.round(tun2socksValue)}%`}
                              </div>
                            </div>
                          </div>
                        ) : (
                          <span className="text-slate-400 dark:text-slate-500">-</span>
                        )}
                      </td>
                    </tr>
                  );
                })}
                {paginatedProxies.length === 0 && (
                  <tr>
                    <td colSpan={proxyKeys.length + 2} className="text-center py-4 text-slate-500 dark:text-slate-400">
                      No proxies found.
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
          {/* Pagination */}
          <div className="flex flex-col sm:flex-row gap-2 mt-4 items-center w-full justify-center text-center">
            <div className="flex w-full sm:w-auto gap-1 justify-center whitespace-nowrap">
              <button
                onClick={() => setPage((p) => Math.max(1, p - 1))}
                disabled={page === 1}
                className="btn btn-outline px-2 py-1 border rounded disabled:opacity-50 flex items-center gap-1 border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 bg-white/90 dark:bg-slate-800/80 w-auto min-w-[64px] text-xs">
                <i className="fa-duotone fa-chevron-left"></i> Prev
              </button>
              <span className="text-slate-700 dark:text-slate-200 flex items-center px-1 text-xs">
                Page {page} of {totalPages}
              </span>
              <button
                onClick={() => setPage((p) => Math.min(totalPages, p + 1))}
                disabled={page === totalPages}
                className="btn btn-outline px-2 py-1 border rounded disabled:opacity-50 flex items-center gap-1 border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 bg-white/90 dark:bg-slate-800/80 w-auto min-w-[64px] text-xs">
                Next <i className="fa-duotone fa-chevron-right"></i>
              </button>
            </div>
            <div className="flex w-full sm:w-auto gap-2 justify-center items-center mt-2 sm:mt-0 text-xs">
              <span className="text-slate-700 dark:text-slate-200">Rows per page:</span>
              <select
                value={rowsPerPage}
                onChange={(e) => {
                  setRowsPerPage(Number(e.target.value));
                  setPage(1);
                }}
                className="border rounded px-2 py-1 border-slate-300 dark:border-slate-700 bg-white/90 dark:bg-slate-900/70 text-slate-900 dark:text-slate-100 w-auto min-w-[64px] text-xs [&>option]:bg-white [&>option]:dark:bg-slate-900">
                {[10, 20, 50, 100].map((n) => (
                  <option key={n} value={n}>
                    {n}
                  </option>
                ))}
              </select>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}

export default WorkingJson;
