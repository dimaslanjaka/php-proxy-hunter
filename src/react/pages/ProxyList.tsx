import React from 'react';
import ReCAPTCHA from 'react-google-recaptcha';
import { ProxyDetails } from '../../../types/proxy';
import { add_ajax_schedule, run_ajax_schedule } from '../../utils/ajaxScheduler';
import { timeAgo } from '../../utils/date.js';
import { noop } from '../../utils/other';
import { useSnackbar } from '../components/Snackbar';
import { createUrl } from '../utils/url';
import { getUserInfo } from '../utils/user';
import ApiUsage from './ProxyList/ApiUsage';
import LogViewer from './ProxyList/LogViewer';
import ModifyCurl from './ProxyList/ModifyCurl';
import ProxySubmission from './ProxyList/ProxySubmission';

/**
 * Handler to re-check a proxy (calls backend API, supports user/pass)
 * @param proxy ProxyDetails
 *
 */
const handleRecheck = async (
  proxy: ProxyDetails,
  showSnackbar: (options: { message: string; type: 'success' | 'danger' }) => void
) => {
  try {
    let body = `proxy=${encodeURIComponent(proxy.proxy)}`;
    if (proxy.username) {
      body += `&username=${encodeURIComponent(proxy.username)}`;
    }
    if (proxy.password) {
      body += `&password=${encodeURIComponent(proxy.password)}`;
    }
    const response = await fetch(createUrl('/php_backend/proxy-checker.php'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded'
      },
      body
    });
    const data = await response.json();
    if (data && data.logEmbedUrl) {
      // removed setLogViewer usage
    }
    if (data && data.message) {
      showSnackbar({ message: data.message, type: 'success' });
    } else {
      showSnackbar({ message: 'Proxy re-check request sent.', type: 'success' });
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

let fetchingProxies = false;
async function getWorkingProxies() {
  if (fetchingProxies) return [];
  fetchingProxies = true;
  // trigger background get working proxies
  fetch(createUrl('/artisan/proxyWorking.php')).catch(noop);
  let result: any = [];
  try {
    const res = await fetch(createUrl('/embed.php?file=working.json'), {
      signal: AbortSignal.timeout(5000)
    });
    if (res.status === 403) {
      fetchingProxies = false;
      return [];
    }
    result = await res.json();
    if (!Array.isArray(result)) {
      fetchingProxies = false;
      return [];
    }
  } catch {
    fetchingProxies = false;
    return [];
  }
  fetchingProxies = false;
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
}

// Helper to fetch proxies (kept at module level)
// (fetchAndSetProxies moved into the component to use setProxies from closure)

function ProxyList() {
  const { showSnackbar } = useSnackbar();
  const [typeFilter, setTypeFilter] = React.useState('');
  const [proxies, setProxies] = React.useState<ProxyDetails[]>([]);
  const [showModal, setShowModal] = React.useState(false);
  const recaptchaRef = React.useRef<ReCAPTCHA>(null);
  const [page, setPage] = React.useState(1);
  const [rowsPerPage, setRowsPerPage] = React.useState(10);
  const [countryFilter, setCountryFilter] = React.useState('');
  const [cityFilter, setCityFilter] = React.useState('');
  const [timezoneFilter, setTimezoneFilter] = React.useState('');
  const [regionFilter, setRegionFilter] = React.useState('');
  const [userId, setUserId] = React.useState<string | null>(null);
  // filters always shown (accordion removed)
  const [loadingProxies, setLoadingProxies] = React.useState(false);
  const isMountedRef = React.useRef(true);

  // Helper to fetch and set proxies using component's setProxies
  const fetchAndSetProxies = async () => {
    const result = await getWorkingProxies();
    if (Array.isArray(result)) setProxies(result);
  };

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
    return filtered;
  }, [proxies, countryFilter, cityFilter, timezoneFilter, typeFilter]);

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

    // Check captcha status
    const checkCaptchaStatus = async () => {
      try {
        const response = await fetch(createUrl('/php_backend/recaptcha.php'), {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
          },
          body: 'status=1'
        });
        const data = await response.json();
        if (!data.success) {
          setShowModal(true);
        } else {
          setShowModal(false);
          // initial load
          await refreshProxies();
        }
      } catch {
        setShowModal(true); // fallback: show modal if error
      }
    };
    checkCaptchaStatus();
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

  const handleRecaptcha = async (token: string | null) => {
    if (token) {
      // Send token to backend for verification
      const response = await fetch(createUrl('/php_backend/recaptcha.php'), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: `g-recaptcha-response=${encodeURIComponent(token)}`
      });
      const data = await response.json();
      if (data.success) {
        setShowModal(false);
        fetchAndSetProxies();
      } else {
        // handle error, e.g., show a message
      }
    }
  };

  // Enqueue proxies with missing timezone to the local AJAX scheduler
  React.useEffect(() => {
    if (proxies.length === 0) return;
    if (!userId) return;
    const updateGeoLocation = (uidToUse: string) => {
      // find proxies that need timezone and limit to first 5
      const candidates = proxies
        .filter(
          (p) => !p.timezone || String(p.timezone).trim().length === 0 || p.timezone === 'N/A' || p.timezone === '-'
        )
        .slice(0, 5);

      if (candidates.length === 0) return;

      candidates.forEach((p) => {
        try {
          const url = createUrl('/geoIpBackground.php');
          add_ajax_schedule(url, { method: 'POST_FORM', data: { uid: uidToUse, proxy: p.proxy } });
        } catch (_e) {
          console.error('[ProxyList] error updating geoIp for proxy:', p.proxy, _e);
        }
      });

      // run scheduler once after enqueuing
      try {
        run_ajax_schedule();
      } catch (e) {
        console.error('[ProxyList] error running ajax scheduler', e);
      }
    };

    updateGeoLocation(userId);
  }, [proxies, userId]);

  return (
    <div className="relative min-h-screen p-4 bg-gray-50 dark:bg-gray-900">
      {/* Modal for reCAPTCHA */}
      {showModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 dark:bg-black/80">
          <div className="bg-white dark:bg-gray-900 rounded-lg shadow-2xl p-8 max-w-md w-full relative animate-fade-in border border-gray-200 dark:border-gray-700">
            <div className="flex justify-center mb-4">
              <i className="fa-duotone fa-shield-halved text-4xl text-blue-600 dark:text-blue-400"></i>
            </div>
            <h2 className="text-xl font-bold mb-2 text-center text-gray-900 dark:text-gray-100">
              Verify you are human
            </h2>
            <p className="mb-4 text-center text-gray-600 dark:text-gray-300">
              Please complete the reCAPTCHA to access the proxy list.
            </p>
            <div className="flex justify-center mb-4">
              <ReCAPTCHA
                sitekey={import.meta.env.VITE_G_RECAPTCHA_V2_SITE_KEY || 'undefined site key'}
                ref={recaptchaRef}
                onChange={handleRecaptcha}
              />
            </div>
            <div className="flex justify-center">
              <button
                className="btn btn-disabled bg-gray-300 dark:bg-gray-700 text-gray-700 dark:text-gray-300 px-4 py-2 rounded disabled:opacity-50 flex items-center gap-2"
                disabled>
                <i className="fa-duotone fa-spinner fa-spin"></i> Continue
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Main content */}
      <div className={showModal ? 'blur-sm pointer-events-none select-none' : ''}>
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
                      title="Re-check proxy"
                      className="inline-flex items-center justify-center p-1 rounded bg-yellow-100 dark:bg-yellow-700 text-yellow-800 dark:text-yellow-200 hover:bg-yellow-200 dark:hover:bg-yellow-600 mr-1"
                      onClick={() => handleRecheck(proxy, showSnackbar)}>
                      <i className="fa-duotone fa-rotate-right"></i>
                    </button>
                    <button
                      title="Copy proxy"
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
                        ? timeAgo(proxy[key])
                        : key === 'type' && proxy[key]
                          ? String(proxy[key])
                              .split('-')
                              .filter(Boolean)
                              .map((t) => {
                                let badgeClass =
                                  'inline-block rounded px-2 py-0.5 mx-0.5 mb-0.5 text-xxs font-semibold align-middle border mr-1';
                                if (t.toLowerCase() === 'http') {
                                  badgeClass +=
                                    ' bg-blue-200 text-blue-900 dark:bg-blue-400/20 dark:text-blue-100 border-blue-300 dark:border-blue-500';
                                } else if (t.toLowerCase() === 'socks4') {
                                  badgeClass +=
                                    ' bg-purple-200 text-purple-900 dark:bg-purple-400/20 dark:text-purple-100 border-purple-300 dark:border-purple-500';
                                } else if (t.toLowerCase() === 'socks5') {
                                  badgeClass +=
                                    ' bg-orange-200 text-orange-900 dark:bg-orange-400/20 dark:text-orange-100 border-orange-300 dark:border-orange-500';
                                } else if (t.toLowerCase() === 'ssl') {
                                  badgeClass +=
                                    ' bg-green-200 text-green-900 dark:bg-green-400/20 dark:text-green-100 border-green-300 dark:border-green-500';
                                } else {
                                  badgeClass +=
                                    ' bg-gray-200 text-gray-900 dark:bg-gray-700 dark:text-gray-100 border-gray-300 dark:border-gray-600';
                                }
                                return (
                                  <span key={t} className={badgeClass}>
                                    {t}
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

      {/* Proxy Submission Section */}
      <ProxySubmission />

      {/* Log Viewer Section (always visible) */}
      <LogViewer />

      {/* Section: How to Modify cURL Timeout in PHP */}
      <ModifyCurl />

      {/* API Usage Section (moved after proxy list) */}
      <ApiUsage />
    </div>
  );
}

export default ProxyList;
