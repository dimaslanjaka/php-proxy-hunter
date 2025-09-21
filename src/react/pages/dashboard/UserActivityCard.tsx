import React from 'react';
import { useTranslation } from 'react-i18next';
import { getUserInfo } from '../../utils/user';

interface ActivityLog {
  id: number;
  action: string;
  timestamp: string;
  details?: string;
}

// Dummy fetch function, replace with real API call
async function fetchCurrentUserActivity(): Promise<ActivityLog[]> {
  // Example: Replace with your backend endpoint
  // const response = await axios.get('/php_backend/user-activity.php');
  // return response.data;
  return [
    { id: 1, action: 'Login', timestamp: '2025-09-10 10:00:00', details: 'Successful login' },
    { id: 2, action: 'Checked proxy', timestamp: '2025-09-10 10:05:00', details: 'Checked 5 proxies' },
    { id: 3, action: 'Logout', timestamp: '2025-09-10 10:10:00', details: 'User logged out' }
  ];
}

export default function UserActivityCard() {
  const { t } = useTranslation();
  const [logs, setLogs] = React.useState<ActivityLog[]>([]);
  const [loading, setLoading] = React.useState(true);
  const [error, setError] = React.useState<string | null>(null);
  const [user, setUser] = React.useState<string>('');

  React.useEffect(() => {
    getUserInfo().then((info) => {
      setUser(info.email || info.username || '');
    });
    fetchCurrentUserActivity()
      .then((data) => setLogs(data))
      .catch(() => setError(t('failed_to_fetch_activity')))
      .finally(() => setLoading(false));
  }, [t]);

  return (
    <div className="flex flex-col items-center justify-center m-4 transition-colors">
      <div className="w-full bg-white dark:bg-gray-800 rounded-lg shadow-lg p-8 transition-colors">
        <h1 className="text-2xl font-bold mb-6 text-center flex items-center justify-center gap-2 text-blue-700 dark:text-blue-300">
          <i className="fa-duotone fa-list-check text-green-500 dark:text-green-400"></i>
          {t('user_activity_title', { user })}
        </h1>
        {loading ? (
          <div className="text-center text-gray-500 dark:text-gray-400">{t('loading')}</div>
        ) : error ? (
          <div className="text-center text-red-600 dark:text-red-400">{error}</div>
        ) : logs.length === 0 ? (
          <div className="text-center text-gray-500 dark:text-gray-400">{t('no_activity_found')}</div>
        ) : (
          <ul className="divide-y divide-gray-200 dark:divide-gray-700">
            {logs.map((log) => (
              <li key={log.id} className="py-4 flex flex-col md:flex-row md:items-center md:justify-between">
                <div>
                  <div className="font-semibold text-gray-800 dark:text-gray-100">{log.action}</div>
                  <div className="text-xs text-gray-500 dark:text-gray-400">{log.timestamp}</div>
                  {log.details && <div className="text-sm text-gray-600 dark:text-gray-300">{log.details}</div>}
                </div>
              </li>
            ))}
          </ul>
        )}
      </div>
    </div>
  );
}
