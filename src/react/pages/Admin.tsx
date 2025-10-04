import React from 'react';
import { getUserInfo } from '../utils/user';
import LogsSection from './admin/LogsSection';
import ManagerPoint from './admin/ManagerPoint';

export default function Admin() {
  const [isAuthenticated, setIsAuthenticated] = React.useState<boolean>(false);
  const [isLoading, setIsLoading] = React.useState<boolean>(true);
  const [isAdmin, setIsAdmin] = React.useState<boolean>(false);

  React.useEffect(() => {
    let mounted = true;
    (async () => {
      try {
        const data = await getUserInfo();
        if (!mounted) {
          console.log('Unmounted, aborting');
          return;
        }
        console.debug('[Admin] getUserInfo response:', data);
        if ((data as any).error || !data.authenticated) {
          window.location.href = '/login';
        } else {
          setIsAuthenticated(true);
          setIsAdmin(data.admin === true);
        }
      } catch (_err) {
        window.location.href = '/login';
      } finally {
        if (mounted) setIsLoading(false);
      }
    })();
    return () => {
      mounted = false;
    };
  }, []);

  if (isLoading) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <div className="animate-spin rounded-full h-32 w-32 border-b-2 border-blue-600 dark:border-blue-300 bg-white dark:bg-gray-800 p-1 rounded-full"></div>
      </div>
    );
  }

  if (!isAuthenticated) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-gray-900">
        <div className="max-w-md p-6 bg-white dark:bg-gray-800 rounded shadow text-center">
          <h2 className="text-xl font-semibold mb-2 text-gray-900 dark:text-gray-100">Not signed in</h2>
          <p className="text-sm text-gray-600 dark:text-gray-300 mb-4">You need to sign in to access the admin area.</p>
          <a
            href="/login"
            className="inline-block px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded"
            title="Login">
            Sign in
          </a>
        </div>
      </div>
    );
  }

  if (!isAdmin) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-gray-900">
        <div className="max-w-md p-6 bg-white dark:bg-gray-800 rounded shadow">
          <h2 className="text-xl font-semibold mb-2 text-gray-900 dark:text-gray-100">Access denied</h2>
          <p className="text-sm text-gray-600 dark:text-gray-300">You do not have permission to view this page.</p>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50 dark:bg-gray-900 transition-colors">
      <ManagerPoint />
      <LogsSection />
    </div>
  );
}
