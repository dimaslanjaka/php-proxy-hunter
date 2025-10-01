import React from 'react';
import { getUserInfo } from '../utils/user';
import DashboardContent from './dashboard/DashboardContent';
import UserActivityCard from './dashboard/UserActivityCard';

export default function Dashboard() {
  const [isAuthenticated, setIsAuthenticated] = React.useState<boolean>(false);
  const [isLoading, setIsLoading] = React.useState<boolean>(true);

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

  if (!isAuthenticated) {
    return null;
  }

  return (
    <>
      <DashboardContent />
      <UserActivityCard />
    </>
  );
}
