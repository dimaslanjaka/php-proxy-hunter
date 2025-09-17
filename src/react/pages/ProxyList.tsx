import React, { useMemo, useState } from 'react';
import CodeBlock from '../components/CodeBlock';
import ReCAPTCHA from 'react-google-recaptcha';
import { createUrl } from '../utils/url';
import { ProxyDetails } from '../../../types/proxy';

// =====================
// API Usage Section
// =====================

function ProxyCheckerApiUsage() {
  // Use string constants to avoid template literal issues in patch context
  const curlExample =
    'curl -X POST \\\n  -d "proxy=1.2.3.4:8080&type=http" \\\n  "' + createUrl('/php_backend/proxy-checker.php') + '"';
  const curlAuthExample =
    'curl -X POST \\\n  -d "proxy=1.2.3.4:8080&type=http&username=myuser&password=mypass" \\\n  "' +
    createUrl('/php_backend/proxy-checker.php') +
    '"';
  const responseExample =
    '{\n  "error": false,\n  "message": "Proxy check is in progress. Please check back later.",\n  "logEmbedUrl": "...",\n  "statusEmbedUrl": "..."\n}';
  return (
    <section className="my-8">
      <div className="p-6 rounded-xl border border-blue-200 dark:border-blue-700 bg-gradient-to-br from-blue-50 to-white dark:from-blue-900/60 dark:to-gray-900 shadow-md transition-colors duration-300">
        <h2 className="flex items-center gap-2 text-lg font-bold mb-3 text-blue-800 dark:text-blue-200">
          <i className="fa-duotone fa-terminal"></i> Proxy Checker API Usage
        </h2>
        <p className="mb-2 text-gray-700 dark:text-gray-200">
          You can check a proxy programmatically using the following API endpoint:
        </p>
        <div className="mb-3">
          <span className="inline-flex items-center gap-2 px-3 py-1 rounded bg-blue-100 dark:bg-blue-800 text-blue-900 dark:text-blue-100 text-xs font-mono border border-blue-200 dark:border-blue-700">
            POST
            <span className="font-semibold">{createUrl('/php_backend/proxy-checker.php')}</span>
          </span>
        </div>
        <p className="mb-1 text-gray-700 dark:text-gray-200 font-semibold">Parameters (form-urlencoded):</p>
        <ul className="list-disc pl-6 text-gray-700 dark:text-gray-200 text-sm mb-3 space-y-1">
          <li>
            <span className="font-semibold">proxy</span>{' '}
            <span className="text-xs text-blue-700 dark:text-blue-300">(required)</span>: Proxy string (e.g.,{' '}
            <code className="bg-gray-100 dark:bg-gray-800 px-1 rounded">1.2.3.4:8080</code>)
          </li>
          <li>
            <span className="font-semibold">type</span>{' '}
            <span className="text-xs text-blue-700 dark:text-blue-300">(optional)</span>: Proxy type (
            <code className="bg-gray-100 dark:bg-gray-800 px-1 rounded">http</code>,{' '}
            <code className="bg-gray-100 dark:bg-gray-800 px-1 rounded">https</code>,{' '}
            <code className="bg-gray-100 dark:bg-gray-800 px-1 rounded">socks4</code>,{' '}
            <code className="bg-gray-100 dark:bg-gray-800 px-1 rounded">socks5</code>)
          </li>
          <li>
            <span className="font-semibold">username</span>{' '}
            <span className="text-xs text-blue-700 dark:text-blue-300">(optional)</span>: Username for proxy auth
          </li>
          <li>
            <span className="font-semibold">password</span>{' '}
            <span className="text-xs text-blue-700 dark:text-blue-300">(optional)</span>: Password for proxy auth
          </li>
        </ul>
        <div className="mb-2">
          <p className="mb-1 text-gray-700 dark:text-gray-200 font-semibold">
            Example <b>curl</b> request:
          </p>
          <CodeBlock language="bash">{curlExample}</CodeBlock>
          <p className="mb-1 mt-4 text-gray-700 dark:text-gray-200 font-semibold">
            Example with <b>username</b> and <b>password</b>:
          </p>
          <CodeBlock language="bash">{curlAuthExample}</CodeBlock>
        </div>
        <div>
          <p className="mb-1 text-gray-700 dark:text-gray-200 font-semibold">Response:</p>
          <CodeBlock language="json">{responseExample}</CodeBlock>
        </div>
        <p className="mt-3 text-gray-600 dark:text-gray-300 text-xs">
          Use the <span className="font-semibold">logEmbedUrl</span> and{' '}
          <span className="font-semibold">statusEmbedUrl</span> from the response to poll for log and status updates.
        </p>
      </div>
    </section>
  );
}
// Handler to re-check a proxy (calls backend API, supports user/pass)
const handleRecheck = async (proxy: ProxyDetails) => {
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
    if (data && data.message) {
      alert(data.message);
    } else {
      alert('Proxy re-check request sent.');
    }
    // Optionally, you could refresh the proxy list here
    // fetchAndSetProxies(setProxies);
  } catch (err) {
    alert('Failed to re-check proxy.');
    console.error(err);
  }
};

// Handler to copy proxy string to clipboard
const handleCopy = (proxy: ProxyDetails) => {
  if (proxy.proxy) {
    navigator.clipboard.writeText(proxy.proxy);
  }
};
// Helper to format time ago
function timeAgo(dateString: string | number | Date) {
  const date = new Date(dateString);
  const now = new Date();
  const seconds = Math.floor((now.getTime() - date.getTime()) / 1000);
  if (isNaN(seconds)) return '';
  if (seconds < 60) return `${seconds}s ago`;
  const minutes = Math.floor(seconds / 60);
  if (minutes < 60) return `${minutes}m ago`;
  const hours = Math.floor(minutes / 60);
  if (hours < 24) return `${hours}h ago`;
  const days = Math.floor(hours / 24);
  if (days < 30) return `${days}d ago`;
  const months = Math.floor(days / 30);
  if (months < 12) return `${months}mo ago`;
  const years = Math.floor(months / 12);
  return `${years}y ago`;
}

let fetchingProxies = false;
async function getWorkingProxies() {
  if (fetchingProxies) return [];
  fetchingProxies = true;
  const result = (await fetch(createUrl('/embed.php?file=working.json'), {
    signal: AbortSignal.timeout(5000)
  })
    .then((res) => res.json())
    .catch(() => [])) as ProxyDetails[];
  fetchingProxies = false;
  for (let i = 0; i < result.length; i++) {
    const proxy = result[i];
    if (proxy.https === 'true') {
      console.log(proxy);
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

// Helper to fetch and set proxies
async function fetchAndSetProxies(setProxies: React.Dispatch<React.SetStateAction<ProxyDetails[]>>) {
  const result = await getWorkingProxies();
  if (Array.isArray(result)) setProxies(result);
}

function ProxyList() {
  const [typeFilter, setTypeFilter] = React.useState('');
  const [proxies, setProxies] = React.useState<ProxyDetails[]>([]);
  const [showModal, setShowModal] = React.useState(false);
  const recaptchaRef = React.useRef<ReCAPTCHA>(null);
  const [page, setPage] = React.useState(1);
  const [rowsPerPage, setRowsPerPage] = React.useState(10);
  const [countryFilter, setCountryFilter] = React.useState('');
  const [cityFilter, setCityFilter] = React.useState('');
  const [timezoneFilter, setTimezoneFilter] = React.useState('');
  const [filterOpen, setFilterOpen] = useState(false);

  // Get ProxyDetails keys for table, reordering specific columns to the end
  const proxyKeys = useMemo(() => {
    if (!proxies[0]) return [];
    const keys = Object.keys(proxies[0]);
    const lastCols = ['useragent', 'webgl_vendor', 'browser_vendor', 'webgl_renderer'];
    const main = keys.filter((k) => !lastCols.includes(k));
    const last = lastCols.filter((k) => keys.includes(k));
    return [...main, ...last];
  }, [proxies]);

  // Unique values for dropdown filters
  const uniqueCountries = useMemo(
    () => Array.from(new Set(proxies.map((p) => p.country).filter(Boolean))).sort(),
    [proxies]
  );
  const uniqueCities = useMemo(() => Array.from(new Set(proxies.map((p) => p.city).filter(Boolean))).sort(), [proxies]);
  const uniqueTimezones = useMemo(
    () => Array.from(new Set(proxies.map((p) => p.timezone).filter(Boolean))).sort(),
    [proxies]
  );

  // Filtered and paginated proxies
  const filteredProxies = useMemo(() => {
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

  const paginatedProxies = useMemo(() => {
    const start = (page - 1) * rowsPerPage;
    return filteredProxies.slice(start, start + rowsPerPage);
  }, [filteredProxies, page, rowsPerPage]);

  const totalPages = Math.ceil(filteredProxies.length / rowsPerPage) || 1;

  // On mount, check captcha status and fetch proxies if verified
  React.useEffect(() => {
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
          fetchAndSetProxies(setProxies);
        }
      } catch {
        setShowModal(true); // fallback: show modal if error
      }
    };
    checkCaptchaStatus();
  }, [setProxies]);

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
        fetchAndSetProxies(setProxies);
      } else {
        // handle error, e.g., show a message
      }
    }
  };

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
        <h1 className="text-2xl font-bold mb-4 text-gray-900 dark:text-gray-100 flex items-center gap-2">
          <i className="fa-duotone fa-list-check"></i> Proxy List
        </h1>
        {/* Filter controls */}
        <div className="mb-4 w-full">
          <button
            type="button"
            className="flex items-center gap-2 px-4 py-2 rounded bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-100 font-semibold w-full sm:w-auto"
            onClick={() => setFilterOpen((open) => !open)}
            aria-expanded={filterOpen}
            aria-controls="proxy-filter-collapse">
            <i className={`fa-duotone ${filterOpen ? 'fa-chevron-up' : 'fa-chevron-down'}`}></i>
            {filterOpen ? 'Hide Filters' : 'Show Filters'}
          </button>
          <div id="proxy-filter-collapse" className={`${filterOpen ? 'block' : 'hidden'}`}>
            <div className="flex flex-col sm:flex-row flex-wrap gap-x-4 gap-y-2 mt-4 items-center w-full">
              <div className="flex flex-col sm:flex-1 w-full sm:w-auto min-w-[150px]">
                <label className="text-gray-700 dark:text-gray-200 mb-1">Type:</label>
                <select
                  value={typeFilter}
                  onChange={(e) => setTypeFilter(e.target.value)}
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
                  onChange={(e) => setCountryFilter(e.target.value)}
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
                  onChange={(e) => setCityFilter(e.target.value)}
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
                <label className="text-gray-700 dark:text-gray-200 mb-1">Timezone:</label>
                <select
                  value={timezoneFilter}
                  onChange={(e) => setTimezoneFilter(e.target.value)}
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
                      onClick={() => handleRecheck(proxy)}>
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
      {/* API Usage Section (moved after proxy list) */}
      <ProxyCheckerApiUsage />
    </div>
  );
}

export default ProxyList;
