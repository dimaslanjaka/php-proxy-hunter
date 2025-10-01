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
        if (!mounted) return;
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
        <div className="animate-spin rounded-full h-32 w-32 border-b-2 border-blue-600"></div>
      </div>
    );
  }

  if (!isAuthenticated || !isAdmin) {
    return null;
  }

  return (
    <div className="min-h-screen bg-gray-50 dark:bg-gray-900 transition-colors">
      <ManagerPoint />
      <LogsSection />
    </div>
  );
}
