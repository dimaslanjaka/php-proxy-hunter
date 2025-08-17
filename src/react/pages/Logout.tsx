import React, { useEffect } from 'react';
import { createUrl } from '../utils/url';

const Logout: React.FC = () => {
  const [done, setDone] = React.useState(false);
  const [error, setError] = React.useState(false);
  const [logoutSuccess, setLogoutSuccess] = React.useState<boolean | null>(null);
  useEffect(() => {
    fetch(createUrl('/php_backend/logout.php'))
      .then((res) => res.json())
      .then((data) => {
        if (data && data.logout === true) {
          setLogoutSuccess(true);
        } else {
          setLogoutSuccess(false);
        }
        setDone(true);
      })
      .catch(() => {
        setError(true);
        setDone(true);
      });
  }, []);

  return (
    <div className="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-gray-900 transition-colors">
      <div className="bg-white dark:bg-gray-800 p-8 rounded shadow text-center transition-colors">
        <h1 className="text-2xl font-bold mb-4 flex items-center justify-center gap-2 text-blue-700 dark:text-blue-300">
          <i className="fal fa-sign-out-alt text-blue-600 dark:text-blue-300"></i>
          Logging out...
        </h1>
        {!done && !error && <p className="text-gray-600 dark:text-gray-300">You are being logged out. Please wait.</p>}
        {done && logoutSuccess === true && (
          <>
            <p className="text-green-600 dark:text-green-400 mb-4 flex items-center justify-center gap-2">
              <i className="fal fa-check-circle"></i>
              You have been logged out.
            </p>
            <a
              href="/login"
              className="text-blue-600 dark:text-blue-400 hover:underline font-semibold flex items-center gap-2">
              <i className="fal fa-sign-in-alt"></i>
              Go to Login
            </a>
          </>
        )}
        {done && logoutSuccess === false && (
          <>
            <p className="text-yellow-600 dark:text-yellow-400 mb-4 flex items-center justify-center gap-2">
              <i className="fal fa-exclamation-triangle"></i>
              Logout response invalid. Please try again.
            </p>
            <a
              href="/login"
              className="text-blue-600 dark:text-blue-400 hover:underline font-semibold flex items-center gap-2">
              <i className="fal fa-sign-in-alt"></i>
              Go to Login
            </a>
          </>
        )}
        {error && (
          <>
            <p className="text-red-600 dark:text-red-400 mb-4 flex items-center justify-center gap-2">
              <i className="fal fa-times-circle"></i>
              Logout failed. Please try again.
            </p>
            <a
              href="/login"
              className="text-blue-600 dark:text-blue-400 hover:underline font-semibold flex items-center gap-2">
              <i className="fal fa-sign-in-alt"></i>
              Go to Login
            </a>
          </>
        )}
      </div>
    </div>
  );
};

export default Logout;
