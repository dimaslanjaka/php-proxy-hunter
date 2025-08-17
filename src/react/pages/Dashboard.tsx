import React from 'react';
import { createUrl } from '../utils/url';
import axios from 'axios';

const Dashboard = () => {
  const [saldo, setSaldo] = React.useState<number | null>(null);
  const [loading, setLoading] = React.useState(true);
  const [error, setError] = React.useState('');

  React.useEffect(() => {
    axios
      .get(createUrl('/php_backend/user-info.php'))
      .then((res) => {
        const data = res.data;
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
    <>
      <div className="min-h-screen bg-gray-100 dark:bg-gray-900 flex flex-col items-center justify-center p-4">
        <div className="w-full max-w-5xl bg-white dark:bg-gray-800 rounded-2xl shadow-2xl p-10 flex flex-col gap-8">
          <div className="flex flex-wrap gap-8 justify-center">
            {/* User Saldo Widget */}
            <div className="flex-1 min-w-[260px] max-w-xs bg-yellow-100 dark:bg-yellow-900 rounded-xl p-8 flex flex-col items-center shadow-md">
              <span className="fa-solid fa-wallet text-yellow-600 dark:text-yellow-300 text-3xl mb-3"></span>
              <div className="text-lg font-semibold text-yellow-700 dark:text-yellow-200 mb-1">Saldo</div>
              <div className="text-3xl font-bold">{loading ? '...' : saldo !== null ? saldo : '—'}</div>
              <button
                type="button"
                onClick={handleBuySaldo}
                className="mt-4 w-full bg-yellow-500 hover:bg-yellow-600 text-white font-semibold py-2 px-4 rounded focus:outline-none focus:ring">
                Buy Saldo (Credit)
              </button>
            </div>
            {/* Placeholder for transaction history */}
            <div className="flex-1 min-w-[260px] max-w-xs bg-gray-100 dark:bg-gray-700 rounded-xl p-8 flex flex-col items-center shadow-md opacity-80 cursor-not-allowed">
              <span className="fa-solid fa-clock-rotate-left text-gray-600 dark:text-gray-300 text-3xl mb-3"></span>
              <div className="text-lg font-semibold text-gray-700 dark:text-gray-200 mb-1">Transaction History</div>
              <div className="text-base text-gray-500 dark:text-gray-300">Coming soon...</div>
            </div>
          </div>
          <div className="mt-8 text-gray-600 dark:text-gray-300 text-center text-lg">
            <span className="fa-regular fa-circle-info mr-2"></span>
            Welcome to your dashboard! More stats and widgets coming soon.
          </div>
          {error && <div className="mt-4 text-red-500 text-center">{error}</div>}
        </div>
      </div>
    </>
  );
};

export default Dashboard;
