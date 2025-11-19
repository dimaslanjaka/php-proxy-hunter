import React, { useState, useEffect } from 'react';
import { useLocation } from 'react-router-dom';
import { sfInstance } from '../components/Link';

/**
 * Outbound Redirect Page
 * Handles /outbound?url= links and redirects after a short delay.
 */
const Outbound: React.FC = () => {
  const location = useLocation();
  const [url, setUrl] = useState<string>('#');

  useEffect(() => {
    const parseUrl = () => {
      const parsed = sfInstance.resolveQueryUrl(window.location.href);
      const url = parsed.url?.base64.decode || '';
      setUrl(url);
      console.log(parsed);
    };
    parseUrl();
  }, [location.search]);

  const isValid = url && /^https?:\/\//.test(url);

  return (
    <>
      <div className="flex flex-col items-center justify-center min-h-screen text-center px-4">
        <div className="max-w-lg w-full bg-white dark:bg-gray-900 rounded-lg shadow-lg dark:shadow-white p-8 border border-gray-200 dark:border-gray-800">
          {isValid ? (
            <>
              <h1 className="text-3xl font-extrabold mb-4 text-blue-700 dark:text-blue-400">Leaving Site</h1>
              <p className="mb-2 text-gray-700 dark:text-gray-300">
                You are about to visit an external site. For your safety, please review the link below before
                proceeding.
              </p>
              <div className="mb-4 p-2 bg-blue-50 dark:bg-gray-800 rounded break-all text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-700">
                {url}
              </div>
              <a
                href={url}
                className="inline-block px-6 py-2 rounded bg-blue-600 dark:bg-blue-700 text-white font-semibold hover:bg-blue-700 dark:hover:bg-blue-800 transition"
                rel="noopener noreferrer"
                target="_blank">
                Continue to site
              </a>
              <p className="mt-4 text-sm text-gray-500 dark:text-gray-400">
                This link will open in a new tab. If you do not trust this link, you can safely close this page.
              </p>
            </>
          ) : (
            <>
              <h1 className="text-2xl font-bold mb-4 text-red-600">Invalid or Missing URL</h1>
              <p className="text-gray-700 dark:text-gray-300">Please check the link and try again.</p>
            </>
          )}
        </div>
      </div>
    </>
  );
};

export default Outbound;
