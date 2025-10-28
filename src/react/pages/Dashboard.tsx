import React from 'react';
import { getUserInfo } from '../utils/user';
import DashboardContent from './dashboard/DashboardContent';
import UserActivityCard from './dashboard/UserActivityCard';

export default function Dashboard() {
  const [isAuthenticated, setIsAuthenticated] = React.useState<boolean>(false);
  const [isLoading, setIsLoading] = React.useState<boolean>(true);
  const [activeTab, setActiveTab] = React.useState<'content' | 'activity'>('content');

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
    <div className="w-full">
      <div className="mb-4 border-b border-gray-200 dark:border-gray-700">
        <ul
          className="flex flex-wrap -mb-px text-sm font-medium text-center text-gray-500 dark:text-gray-400"
          role="tablist">
          <li className="me-2" role="presentation">
            <button
              type="button"
              role="tab"
              aria-controls="user-information"
              aria-selected={activeTab === 'content'}
              className={`inline-block p-4 rounded-t-lg border-b-2 ${
                activeTab === 'content'
                  ? 'text-blue-600 border-blue-600 dark:text-blue-500 dark:border-blue-500 bg-gray-100 dark:bg-gray-800'
                  : 'border-transparent hover:text-gray-600 hover:border-gray-300 dark:hover:text-gray-300'
              }`}
              onClick={() => setActiveTab('content')}>
              User information
            </button>
          </li>
          <li role="presentation">
            <button
              type="button"
              role="tab"
              aria-controls="user-activity"
              aria-selected={activeTab === 'activity'}
              className={`inline-block p-4 rounded-t-lg border-b-2 ${
                activeTab === 'activity'
                  ? 'text-blue-600 border-blue-600 dark:text-blue-500 dark:border-blue-500 bg-gray-100 dark:bg-gray-800'
                  : 'border-transparent hover:text-gray-600 hover:border-gray-300 dark:hover:text-gray-300'
              }`}
              onClick={() => setActiveTab('activity')}>
              Activity
            </button>
          </li>
        </ul>
      </div>

      <div id="dashboard-tabs-content">
        <div
          id="user-information"
          role="tabpanel"
          aria-labelledby="user-information"
          className={`${activeTab === 'content' ? '' : 'hidden'}`}>
          <DashboardContent />
        </div>
        <div
          id="user-activity"
          role="tabpanel"
          aria-labelledby="user-activity"
          className={`${activeTab === 'activity' ? '' : 'hidden'}`}>
          <UserActivityCard />
        </div>
      </div>
    </div>
  );
}
