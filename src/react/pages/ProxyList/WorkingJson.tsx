import React from 'react';
import { useTranslation } from 'react-i18next';
import { ProxyDetails } from '../../../../types/proxy';
import { add_ajax_schedule, run_ajax_schedule } from '../../../utils/ajaxScheduler';
import { timeAgo } from '../../../utils/date/timeAgo.js';
import { noop } from '../../../utils/other';
import { useSnackbar } from '../../components/Snackbar';
import { createUrl } from '../../utils/url';

import { getUserInfo } from '../../utils/user';
import { getProxyTypeColorClass } from '../../utils/proxyColors';
import ApiUsage from './ApiUsage';

import ModifyCurl from './ModifyCurl';
import { checkProxy } from '../../utils/proxy';

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

// Handler to copy proxy string to clipboard
const handleCopy = (proxy: ProxyDetails) => {
  if (proxy.proxy) {
    navigator.clipboard.writeText(proxy.proxy);
  }
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
    // refresh working.json
    await fetch(createUrl('/artisan/proxyWorking.php')).catch(noop);
    let result: any = [];
    try {
      const res = await fetch(createUrl('/embed.php?file=working.json'), {
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
    return [...main, ...last];
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
    // fetch processes.php
    fetch(createUrl('/php_backend/processes.php')).catch(noop);
    // fetch artisan/proxyCheckerStarter.php to start background checks every 5 minutes
    const lastCheck = localStorage.getItem('lastProxyCheckStart') || '0';
    const now = Date.now();
    if (now - parseInt(lastCheck, 10) > 5 * 60 * 1000) {
      fetch(createUrl('/artisan/proxyCheckerStarter.php')).catch(noop);
      localStorage.setItem('lastProxyCheckStart', now.toString());
    }

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
    add_ajax_schedule(createUrl('/geoIpBackground.php'), {
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
    <div className="relative min-h-screen p-4 bg-gray-50 dark:bg-gray-900">
      {/* Main content */}
      <div>
        <div className="flex items-center justify-between mb-4">
          <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100 flex items-center gap-2">
            <i className="fa-duotone fa-list-check"></i> Proxy List
          </h1>
          <button
            className="ml-0 sm:ml-4 px-3 py-1 bg-blue-500 hover:bg-blue-600 text-white text-xs font-semibold rounded shadow transition-colors flex items-center gap-1 flex-shrink-0"
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
                <label className="text-gray-700 dark:text-gray-200 mb-1">Search:</label>
                <input
                  type="search"
                  value={searchQuery}
                  onChange={(e) => {
                    setSearchQuery(e.target.value);
                    setPage(1);
                  }}
                  placeholder="Search proxies..."
                  className="border rounded px-2 py-1 dark:bg-gray-800 dark:text-gray-100 w-full"
                />
              </div>

              <div className="flex flex-col sm:flex-1 w-full sm:w-auto min-w-[150px]">
                <label className="text-gray-700 dark:text-gray-200 mb-1">Type:</label>
                <select
                  value={typeFilter}
                  onChange={(e) => {
                    setTypeFilter(e.target.value);
                    setPage(1);
                  }}
                  className="border rounded px-2 py-1 dark:bg-gray-800 dark:text-gray-100 w-full">
                  <option value="">All Types</option>
                  <option value="http">HTTP</option>
                  <option value="socks4">SOCKS4</option>
                  <option value="socks5">SOCKS5</option>
                  <option value="ssl">SSL</option>
                </select>
              </div>
              <div className="flex flex-col sm:flex-1 w-full sm:w-auto min-w-[150px]">
                <label className="text-gray-700 dark:text-gray-200 mb-1">Country:</label>
                <select
                  value={countryFilter}
                  onChange={(e) => {
                    setCountryFilter(e.target.value);
                    setPage(1);
                  }}
                  className="border rounded px-2 py-1 dark:bg-gray-800 dark:text-gray-100 w-full">
                  <option value="">All Countries</option>
                  {uniqueCountries.map((country) => (
                    <option key={country} value={country}>
                      {country}
                    </option>
                  ))}
                </select>
              </div>
              <div className="flex flex-col sm:flex-1 w-full sm:w-auto min-w-[150px]">
                <label className="text-gray-700 dark:text-gray-200 mb-1">City:</label>
                <select
                  value={cityFilter}
                  onChange={(e) => {
                    setCityFilter(e.target.value);
                    setPage(1);
                  }}
                  className="border rounded px-2 py-1 dark:bg-gray-800 dark:text-gray-100 w-full">
                  <option value="">All Cities</option>
                  {uniqueCities.map((city) => (
                    <option key={city} value={city}>
                      {city}
                    </option>
                  ))}
                </select>
              </div>
              <div className="flex flex-col sm:flex-1 w-full sm:w-auto min-w-[150px]">
                <label className="text-gray-700 dark:text-gray-200 mb-1">Region:</label>
                <select
                  value={regionFilter}
                  onChange={(e) => {
                    setRegionFilter(e.target.value);
                    setPage(1);
                  }}
                  className="border rounded px-2 py-1 dark:bg-gray-800 dark:text-gray-100 w-full">
                  <option value="">All Regions</option>
                  {uniqueRegions.map((r) => (
                    <option key={r} value={r}>
                      {r}
                    </option>
                  ))}
                </select>
              </div>
              <div className="flex flex-col sm:flex-1 w-full sm:w-auto min-w-[150px]">
                <label className="text-gray-700 dark:text-gray-200 mb-1">Timezone:</label>
                <select
                  value={timezoneFilter}
                  onChange={(e) => {
                    setTimezoneFilter(e.target.value);
                    setPage(1);
                  }}
                  className="border rounded px-2 py-1 dark:bg-gray-800 dark:text-gray-100 w-full">
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
        <div className="overflow-x-auto rounded-lg shadow border border-gray-200 dark:border-gray-700">
          <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead className="bg-gray-100 dark:bg-gray-800">
              <tr>
                <th className="px-2 py-1 text-xs font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wider border-b border-gray-200 dark:border-gray-700 text-center">
                  Actions
                </th>
                {proxyKeys.map((key) => (
                  <th
                    key={key}
                    className="px-2 py-1 text-xs font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wider border-b border-gray-200 dark:border-gray-700">
                    {key}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody className="bg-white dark:bg-gray-900 divide-y divide-gray-100 dark:divide-gray-800">
              {paginatedProxies.map((proxy, idx) => (
                <tr key={idx} className="hover:bg-gray-50 dark:hover:bg-gray-800">
                  <td className="px-2 py-1 text-xs whitespace-nowrap border-b border-gray-100 dark:border-gray-800 text-center">
                    <button
                      title={t('recheck_proxy')}
                      className="inline-flex items-center justify-center p-1 rounded bg-yellow-100 dark:bg-yellow-700 text-yellow-800 dark:text-yellow-200 hover:bg-yellow-200 dark:hover:bg-yellow-600 mr-1"
                      onClick={() => handleRecheck(proxy, showSnackbar)}>
                      <i className="fa-duotone fa-rotate-right"></i>
                    </button>
                    <button
                      title={t('copy_proxy')}
                      className="inline-flex items-center justify-center p-1 rounded bg-gray-200 dark:bg-gray-800 text-gray-800 dark:text-gray-100 hover:bg-gray-300 dark:hover:bg-gray-700 border border-gray-300 dark:border-gray-600"
                      onClick={() => handleCopy(proxy)}>
                      <i className="fa-duotone fa-copy"></i>
                    </button>
                  </td>
                  {proxyKeys.map((key) => (
                    <td
                      key={key}
                      className="px-2 py-1 text-xs whitespace-nowrap border-b border-gray-100 dark:border-gray-800 text-gray-900 dark:text-gray-100">
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
                          : key === 'latency' && proxy[key] !== undefined && proxy[key] !== null
                            ? `${proxy[key]} ms`
                            : String(proxy[key] ?? '')}
                    </td>
                  ))}
                </tr>
              ))}
              {paginatedProxies.length === 0 && (
                <tr>
                  <td colSpan={proxyKeys.length} className="text-center py-4 text-gray-500 dark:text-gray-400">
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
              className="btn btn-outline px-2 py-1 border rounded disabled:opacity-50 flex items-center gap-1 border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 w-auto min-w-[64px] text-xs">
              <i className="fa-duotone fa-chevron-left"></i> Prev
            </button>
            <span className="text-gray-700 dark:text-gray-200 flex items-center px-1 text-xs">
              Page {page} of {totalPages}
            </span>
            <button
              onClick={() => setPage((p) => Math.min(totalPages, p + 1))}
              disabled={page === totalPages}
              className="btn btn-outline px-2 py-1 border rounded disabled:opacity-50 flex items-center gap-1 border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 w-auto min-w-[64px] text-xs">
              Next <i className="fa-duotone fa-chevron-right"></i>
            </button>
          </div>
          <div className="flex w-full sm:w-auto gap-2 justify-center items-center mt-2 sm:mt-0 text-xs">
            <span className="text-gray-700 dark:text-gray-200">Rows per page:</span>
            <select
              value={rowsPerPage}
              onChange={(e) => {
                setRowsPerPage(Number(e.target.value));
                setPage(1);
              }}
              className="border rounded px-2 py-1 dark:bg-gray-800 dark:text-gray-100 border-gray-300 dark:border-gray-700 w-auto min-w-[64px] text-xs">
              {[10, 20, 50, 100].map((n) => (
                <option key={n} value={n}>
                  {n}
                </option>
              ))}
            </select>
          </div>
        </div>
      </div>

      {/* Section: How to Modify cURL Timeout in PHP */}
      <ModifyCurl />

      {/* API Usage Section (moved after proxy list) */}
      <ApiUsage />
    </div>
  );
}

export default WorkingJson;
