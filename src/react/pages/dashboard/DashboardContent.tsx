import React from 'react';
import { useTranslation } from 'react-i18next';
import { getUserInfo } from '../../utils/user';

const DashboardContent = () => {
  const { t } = useTranslation();
  const [saldo, setSaldo] = React.useState<number | null>(null);
  const [loading, setLoading] = React.useState(true);
  const [error, setError] = React.useState('');

  React.useEffect(() => {
    getUserInfo()
      .then((data) => {
        if (typeof data.saldo === 'number') {
          setSaldo(data.saldo);
        }
        setLoading(false);
      })
      .catch(() => {
        setError('Failed to load user info.');
        setLoading(false);
      });
  }, []);

  const handleBuySaldo = () => {
    // TODO: Integrate with payment/credit system
    alert('Redirecting to buy saldo (credit)...');
  };

  return (
    <div className="flex flex-col items-center justify-center m-4 transition-colors">
      <div className="w-full bg-white dark:bg-gray-800 rounded-lg shadow-lg p-8 transition-colors border border-gray-200 dark:border-gray-700 flex flex-col gap-10">
        <div className="flex flex-wrap gap-8 justify-center">
          {/* User Saldo Widget */}
          <div className="flex-1 min-w-[260px] w-full bg-yellow-50 dark:bg-yellow-900/60 rounded-2xl p-8 flex flex-col items-center shadow-md border border-yellow-200 dark:border-yellow-800">
            <span className="fa-solid fa-wallet text-yellow-600 dark:text-yellow-300 text-4xl mb-4"></span>
            <div className="text-lg font-semibold text-yellow-700 dark:text-yellow-200 mb-1">{t('point', 'Point')}</div>
            <div className="text-3xl font-bold text-yellow-800 dark:text-yellow-100">
              {loading ? '...' : saldo !== null ? saldo : '—'}
            </div>
            <button
              type="button"
              onClick={handleBuySaldo}
              className="mt-5 w-full bg-yellow-500 hover:bg-yellow-600 text-white font-semibold py-2 px-4 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-400 dark:focus:ring-yellow-600 transition-all duration-200">
              {t('recharge_points', 'Recharge Points')}
            </button>
          </div>
          {/* Placeholder for transaction history */}
          <div className="flex-1 min-w-[260px] w-full bg-gray-100 dark:bg-gray-700/70 rounded-2xl p-8 flex flex-col items-center shadow-md border border-gray-200 dark:border-gray-700 opacity-80 cursor-not-allowed">
            <span className="fa-solid fa-clock-rotate-left text-gray-600 dark:text-gray-300 text-4xl mb-4"></span>
            <div className="text-lg font-semibold text-gray-700 dark:text-gray-200 mb-1">Transaction History</div>
            <div className="text-base text-gray-500 dark:text-gray-300">Coming soon...</div>
          </div>
        </div>
        <div className="mt-10 text-gray-600 dark:text-gray-300 text-center text-lg flex items-center justify-center gap-2">
          <span className="fa-regular fa-circle-info"></span>
          <span>Welcome to your dashboard! More stats and widgets coming soon.</span>
        </div>
        {error && <div className="mt-4 text-red-500 text-center">{error}</div>}
      </div>
    </div>
  );
};

export default DashboardContent;
