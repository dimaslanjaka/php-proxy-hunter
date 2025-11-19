import React, { useState } from 'react';
import { createUrl } from '../../utils/url';
import { useTranslation } from 'react-i18next';

/**
 * Section: How to Modify cURL Timeout in PHP
 *
 * This section explains how to change the cURL timeout in the PHP proxy checker backend.
 */

const ModifyCurl: React.FC = () => {
  const [timeout, setTimeout] = useState(60);
  const [loading, setLoading] = useState(false);
  const [result, setResult] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const { t } = useTranslation();

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setResult(null);
    setError(null);
    try {
      const response = await fetch(createUrl('php_backend/proxy-checker.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `set_curl_timeout=${encodeURIComponent(timeout)}`
      });
      const data = await response.json().catch(() => null);
      if (data && data.error === false && data.message) {
        setResult(data.message);
      } else if (data && data.message) {
        setError(data.message);
      } else {
        setError(t('unknown_server_response'));
      }
    } catch (_err) {
      setError(t('failed_send_request'));
    }
    setLoading(false);
  };

  return (
    <section className="my-8">
      <div className="bg-white dark:bg-gray-900 rounded-xl shadow-lg dark:shadow-white border border-blue-200 dark:border-blue-700 p-6 transition-colors duration-300">
        <h2 className="text-lg font-bold text-blue-800 dark:text-blue-200 flex items-center gap-2 mb-2">
          <i className="fa-duotone fa-clock"></i> Modify cURL Timeout in PHP
        </h2>
        <form onSubmit={handleSubmit} className="flex flex-col gap-4 max-w-xs">
          <label className="flex flex-col gap-1">
            <span className="text-gray-700 dark:text-gray-200 font-medium">cURL Timeout (seconds):</span>
            <input
              type="number"
              min={1}
              max={120}
              value={timeout}
              onChange={(e) => setTimeout(Number(e.target.value))}
              className="border rounded px-2 py-1 dark:bg-gray-800 dark:text-gray-100 w-full"
              required
            />
          </label>
          <button
            type="submit"
            className="btn btn-primary px-4 py-2 rounded bg-blue-600 text-white font-semibold disabled:opacity-50"
            disabled={loading}>
            {loading ? t('saving') : t('set_timeout')}
          </button>
        </form>
        {result && <div className="mt-4 text-green-700 dark:text-green-300 font-semibold">{result}</div>}
        {error && <div className="mt-4 text-red-700 dark:text-red-300 font-semibold">{error}</div>}
        <div className="mt-6 text-xs text-gray-600 dark:text-gray-400">
          This will update the <code>CURLOPT_TIMEOUT</code> value in the backend for proxy checking. Applies to all
          future checks.
        </div>
      </div>
    </section>
  );
};

export default ModifyCurl;
