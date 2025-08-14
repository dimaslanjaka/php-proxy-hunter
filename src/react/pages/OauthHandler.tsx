import React from 'react';
import { useLocation } from 'react-router-dom';
import { createUrl } from '../utils/url';
import Link from '../components/Link';

const OauthHandler = () => {
  const location = useLocation();
  const [result, setResult] = React.useState<null | { success: boolean; email?: string; error?: string }>(null);
  const [loading, setLoading] = React.useState(true);

  // Prevent double execution in React 18 StrictMode by using a ref
  const hasRunRef = React.useRef(false);
  React.useEffect(() => {
    if (hasRunRef.current) return;
    hasRunRef.current = true;
    const params = new URLSearchParams(location.search);
    const code = params.get('code');
    const state = params.get('state');
    if (!code) {
      setResult({ success: false, error: 'No OAuth code found. Please try again.' });
      setLoading(false);
      return;
    }
    setLoading(true);
    const googleRedirectUrl = import.meta.env.VITE_GOOGLE_REDIRECT_URL || createUrl('/oauth/google');
    fetch(
      createUrl('/php_backend/google-oauth.php', { redirect_uri: googleRedirectUrl, 'google-oauth-callback': code }),
      {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ code, state })
      }
    )
      .then((res) => res.json())
      .then((data) => {
        setResult(data);
        setLoading(false);
      })
      .catch(() => {
        setResult({ success: false, error: 'OAuth failed. Please try again.' });
        setLoading(false);
      });
  }, [location]);

  return (
    <>
      <div id="oauth-handler" className="min-h-screen flex items-center justify-center bg-gray-100 dark:bg-gray-900">
        <div className="bg-white dark:bg-gray-800 p-8 rounded shadow-md w-full max-w-md text-center">
          {loading ? (
            <>
              <h2 className="text-2xl font-bold mb-6 text-blue-600 dark:text-white">Processing OAuth...</h2>
              <p className="text-gray-700 dark:text-gray-200 mb-4">Please wait while we log you in with Google.</p>
            </>
          ) : (
            <>
              {result?.success ? (
                <>
                  <h2 className="text-2xl font-bold mb-6 text-green-600 dark:text-green-400">Login Successful!</h2>
                  <p className="text-gray-700 dark:text-gray-200 mb-2">
                    Welcome{result.email ? `, ${result.email}` : ''}!
                  </p>
                </>
              ) : (
                <>
                  <h2 className="text-2xl font-bold mb-6 text-red-600 dark:text-red-400">Login Failed</h2>
                  <p className="text-gray-700 dark:text-gray-200 mb-2">{result?.error || 'Unknown error.'}</p>
                </>
              )}
              <div className="flex flex-col gap-2 mt-4 sm:flex-row sm:justify-center sm:gap-4">
                <Link
                  href="/dashboard"
                  className={`w-full sm:w-48 flex items-center justify-center px-4 py-2 rounded font-semibold shadow transition focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-opacity-50
                    ${
                      result && !result.success
                        ? 'bg-gray-300 text-gray-500 cursor-not-allowed pointer-events-none dark:bg-gray-700 dark:text-gray-400'
                        : 'bg-blue-600 text-white hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600'
                    }
                  `}
                  aria-disabled={result && !result.success}
                  tabIndex={result && !result.success ? -1 : 0}>
                  Go to Dashboard <i className="fal fa-arrow-right ml-1" />
                </Link>
                <Link
                  href="/login"
                  className="w-full sm:w-48 flex items-center justify-center px-4 py-2 rounded bg-gray-200 text-blue-700 font-semibold shadow hover:bg-gray-300 transition dark:bg-gray-700 dark:text-blue-300 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-opacity-50">
                  <i className="fal fa-arrow-left mr-1" /> Back to Login
                </Link>
              </div>
            </>
          )}
        </div>
      </div>
    </>
  );
};

export default OauthHandler;
