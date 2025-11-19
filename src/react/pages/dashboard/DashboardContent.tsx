import React from 'react';
import { useTranslation } from 'react-i18next';
import { getUserInfo, SingleUserInfo } from '../../utils/user';

const DashboardContent: React.FC = () => {
  const { t } = useTranslation();
  const [user, setUser] = React.useState<SingleUserInfo | null>(null);
  const [loading, setLoading] = React.useState(true);
  const [error, setError] = React.useState('');

  React.useEffect(() => {
    let mounted = true;
    getUserInfo()
      .then((data) => {
        if (!mounted) return;
        setUser(data);
        setLoading(false);
      })
      .catch(() => {
        if (!mounted) return;
        setError('Failed to load user info.');
        setLoading(false);
      });
    return () => {
      mounted = false;
    };
  }, []);

  const displayName = React.useMemo(() => {
    if (!user) return '';
    const nameParts = [user.first_name, user.last_name].filter(Boolean);
    return nameParts.length ? nameParts.join(' ') : user.username || user.email || '—';
  }, [user]);

  const handleTopUp = React.useCallback(() => {
    // placeholder: integrate with payment/credit system
    // keep simple for now; can be replaced with modal or redirect
    alert(t('recharge_points'));
  }, [t]);

  return (
    <div className="flex flex-col items-center justify-center m-4 transition-colors">
      <div className="w-full bg-white dark:bg-gray-800 rounded-lg shadow-lg dark:shadow-white p-8 transition-colors border border-gray-200 dark:border-gray-700 flex flex-col gap-10">
        <h2 className="text-2xl font-semibold text-gray-800 dark:text-gray-100 mb-6">Dashboard</h2>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div className="bg-yellow-50 dark:bg-yellow-900/60 rounded-2xl p-6 flex flex-col items-start shadow-md dark:shadow-white border border-yellow-200 dark:border-yellow-800">
            <div className="flex items-center gap-4 w-full">
              <span className="fa-solid fa-wallet text-yellow-600 dark:text-yellow-300 text-3xl"></span>
              <div>
                <div className="text-sm font-medium text-yellow-700 dark:text-yellow-200">{t('points')}</div>
                <div className="text-3xl font-bold text-yellow-800 dark:text-yellow-100">
                  {loading ? '...' : typeof user?.saldo === 'number' ? user!.saldo : '—'}
                </div>
                <button
                  type="button"
                  onClick={handleTopUp}
                  className="mt-5 w-full bg-yellow-500 hover:bg-yellow-600 text-white font-semibold py-2 px-4 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-400 dark:focus:ring-yellow-600 transition-all duration-200">
                  {t('recharge_points')}
                </button>
              </div>
            </div>
          </div>

          <div className="bg-gray-50 dark:bg-gray-900/60 rounded-2xl p-6 shadow-md dark:shadow-white border border-gray-200 dark:border-gray-700">
            <div className="text-sm text-gray-500 dark:text-gray-300 mb-2">{t('user_information')}</div>
            {loading ? (
              <div className="text-gray-600 dark:text-gray-300">...</div>
            ) : user ? (
              <div className="space-y-2 text-gray-800 dark:text-gray-100">
                <div>
                  <span className="font-medium">{t('name')}: </span>
                  <span>{displayName}</span>
                </div>
                {user.email && (
                  <div>
                    <span className="font-medium">{t('email')}: </span>
                    <span>{user.email}</span>
                  </div>
                )}
                {user.username && (
                  <div>
                    <span className="font-medium">{t('username')}: </span>
                    <span>{user.username}</span>
                  </div>
                )}
                {user.phone && (
                  <div>
                    <span className="font-medium">{t('phone')}: </span>
                    <span>{user.phone}</span>
                  </div>
                )}
              </div>
            ) : (
              <div className="text-red-500">{error || t('no_user_found')}</div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
};

export default DashboardContent;
