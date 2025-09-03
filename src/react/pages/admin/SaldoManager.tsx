import { useTranslation } from 'react-i18next';
import { addSaldoToUser, getListOfUsers, getUserInfo } from '../../utils/user.js';
import { useNavigate } from 'react-router-dom';
import React, { useState } from 'react';
import AddSaldoForm from './AddSaldoForm';
import EditPasswordForm from './EditPasswordForm';
import EditSaldoForm from './EditSaldoForm';

export default function SaldoManager() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const [users, setUsers] = useState<Awaited<ReturnType<typeof getListOfUsers>>>([]);
  const [selectedUser, setSelectedUser] = useState<string | null>(null);
  const [saldo, setSaldo] = useState('');
  const [error, setError] = useState<string | null>(null);

  // Helper to get selected user's saldo
  function getSelectedUserSaldo(): number {
    if (!selectedUser) return 0;
    const user = users.find((u: any) => u.email === selectedUser);
    if (!user) return 0;
    return user.saldo ?? 0;
  }

  React.useEffect(() => {
    // Get current logged-in user
    getUserInfo()
      .then((user) => {
        if (user && !user.admin) {
          // Current user is not admin, redirect using react-router-dom
          navigate('/');
        }
      })
      .catch(() => {
        setError(t('failed_to_fetch_user_info'));
      });
    // Get list of users from the server
    const fetchUsers = async () => {
      try {
        const result = await getListOfUsers();
        setUsers(result);
        if (result && result.length > 0) {
          setSelectedUser(result[0].email || '');
        }
      } catch (_e) {
        setUsers([]);
        setSelectedUser(null);
      }
    };
    fetchUsers();
  }, []);

  const handleSubmit = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    setError(null);
    if (selectedUser) {
      try {
        await addSaldoToUser(selectedUser, parseFloat(saldo));
        // Re-fetch users to update saldo
        try {
          const result = await getListOfUsers();
          setUsers(result);
        } catch (_err) {
          setError(t('failed_to_refresh_user_list'));
        }
        setSaldo('');
      } catch (err: any) {
        setError(err?.message || t('add_saldo_error'));
      }
    } else {
      setError(t('add_saldo_select_user'));
    }
  };

  return (
    <div className="min-h-screen bg-gray-50 dark:bg-gray-900 flex flex-col items-center justify-center p-4 transition-colors">
      <div className="w-full bg-white dark:bg-gray-800 rounded-lg shadow-lg p-8 transition-colors">
        <h1 className="text-2xl font-bold mb-6 text-center flex items-center justify-center gap-2 text-blue-700 dark:text-blue-300">
          <i className="text-green-500 dark:text-green-400 fa fa-money-bill-wave"></i>
          {t('saldo_manager_title')}
        </h1>
        <form onSubmit={handleSubmit} className="space-y-6" autoComplete="off">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6 items-stretch">
            <div>
              <label htmlFor="user" className="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-200">
                {t('select_user_label')}
              </label>
              <select
                id="user"
                className="block w-full rounded-md border-gray-300 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 dark:focus:border-blue-400 dark:focus:ring-blue-900 focus:ring-opacity-50 transition-colors"
                value={selectedUser ?? ''}
                onChange={(e) => setSelectedUser(e.target.value)}
                disabled={users.length === 0}
                autoComplete="off">
                {Array.isArray(users) && users.length === 0 ? (
                  <option value="">{t('loading_users')}</option>
                ) : Array.isArray(users) ? (
                  users.map((user) => (
                    <option
                      key={user.email ?? user.id ?? user.username ?? user.first_name ?? Math.random()}
                      value={user.email}>
                      {user.email}
                    </option>
                  ))
                ) : (
                  <option value="">{t('no_users_found')}</option>
                )}
              </select>
            </div>
            <div>
              <AddSaldoForm
                userId={selectedUser}
                currentSaldo={getSelectedUserSaldo()}
                onSuccess={async () => {
                  // Re-fetch users to update saldo
                  try {
                    const result = await getListOfUsers();
                    setUsers(result);
                  } catch (_err) {
                    setError('Failed to refresh user list.');
                  }
                }}
              />
            </div>
            <div>
              <EditPasswordForm userId={selectedUser} onSuccess={() => {}} />
            </div>
            <div>
              <EditSaldoForm
                userId={selectedUser}
                currentSaldo={getSelectedUserSaldo()}
                onSuccess={async () => {
                  // Re-fetch users to update saldo
                  try {
                    const result = await getListOfUsers();
                    setUsers(result);
                  } catch (_err) {
                    setError('Failed to refresh user list.');
                  }
                }}
              />
            </div>
          </div>
          {error && <div className="mb-2 text-center text-red-600 dark:text-red-400 text-sm font-medium">{error}</div>}
        </form>
      </div>
    </div>
  );
}
