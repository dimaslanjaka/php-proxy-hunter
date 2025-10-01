import React from 'react';
import { createUrl } from '../utils/url';
import LogsSection from './admin/LogsSection';
import ManagerPoint from './admin/ManagerPoint';

export default function Admin() {
  const [isAuthenticated, setIsAuthenticated] = React.useState<boolean>(false);
  const [isLoading, setIsLoading] = React.useState<boolean>(true);

  React.useEffect(() => {
    const $url = createUrl('/php_backend/user-info.php');
    fetch($url)
      .then((response) => response.json())
      .then((data) => {
        if (data.error || !data.authenticated) {
          window.location.href = '/login';
        } else {
          setIsAuthenticated(true);
        }
      })
      .catch(() => {
        window.location.href = '/login';
      })
      .finally(() => {
        setIsLoading(false);
      });
  }, []);

  if (isLoading) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <div className="animate-spin rounded-full h-32 w-32 border-b-2 border-blue-600"></div>
      </div>
    );
  }

  if (!isAuthenticated) {
    return null;
  }

  return (
    <div className="min-h-screen bg-gray-50 dark:bg-gray-900 transition-colors">
      <ManagerPoint />
      <LogsSection />
    </div>
  );
}
