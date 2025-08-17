import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { addSaldoToUser, getListOfUsers, getUserInfo } from '../utils/user';
import { toRupiah } from '../../utils/number';

export default function Admin() {
  const navigate = useNavigate();
  const [users, setUsers] = useState<Awaited<ReturnType<typeof getListOfUsers>>>([]);
  const [selectedUser, setSelectedUser] = useState<string | null>(null);
  const [saldo, setSaldo] = useState('');
  const [error, setError] = useState<string | null>(null);

  // Helper to get selected user's saldo
  function getSelectedUserSaldo(): number {
    if (!selectedUser) return 0;
    const user = users.find((u: any) => String(u.id) === selectedUser || u.email === selectedUser);
    if (!user) return 0;
    return user.saldo ?? 0;
  }

  // Helper to get the new saldo after addition
  function getNewSaldo(): number {
    const current = getSelectedUserSaldo();
    const add = parseFloat(saldo);
    if (isNaN(add)) return current;
    return current + add;
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
        setError('Failed to fetch user info.');
      });
    // Get list of users from the server
    const fetchUsers = async () => {
      try {
        const result = await getListOfUsers();
        setUsers(result);
        if (result && result.length > 0) {
          setSelectedUser(result[0].id !== undefined ? String(result[0].id) : '');
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
          setError('Failed to refresh user list.');
        }
        setSaldo('');
      } catch (err: any) {
        setError(err?.message || 'Failed to add saldo.');
      }
    } else {
      setError('Please select a user to add saldo.');
    }
  };

  return (
    <div className="min-h-screen bg-gray-50 dark:bg-gray-900 flex flex-col items-center justify-center p-4 transition-colors">
      <div className="w-full max-w-md bg-white dark:bg-gray-800 rounded-lg shadow-lg p-8 transition-colors">
        <h1 className="text-2xl font-bold mb-6 text-center flex items-center justify-center gap-2 text-blue-700 dark:text-blue-300">
          <i className="text-green-500 dark:text-green-400 fa fa-plus"></i>
          Add Saldo
        </h1>
        <form onSubmit={handleSubmit} className="space-y-6">
          <div>
            <label htmlFor="user" className="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-200">
              Select User
            </label>
            <select
              id="user"
              className="block w-full rounded-md border-gray-300 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 dark:focus:border-blue-400 dark:focus:ring-blue-900 focus:ring-opacity-50 transition-colors"
              value={selectedUser ?? ''}
              onChange={(e) => setSelectedUser(e.target.value)}
              disabled={users.length === 0}>
              {Array.isArray(users) && users.length === 0 ? (
                <option value="">Loading users...</option>
              ) : Array.isArray(users) ? (
                users.map((user) => (
                  <option
                    key={user.id ?? user.email ?? user.username ?? user.first_name ?? Math.random()}
                    value={user.id ?? user.email}>
                    {user.email}
                  </option>
                ))
              ) : (
                <option value="">No users found</option>
              )}
            </select>
          </div>
          <div>
            <label htmlFor="saldo" className="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-200">
              Saldo Amount
            </label>
            <input
              id="saldo"
              type="number"
              className="block w-full rounded-md border-gray-300 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 dark:focus:border-blue-400 dark:focus:ring-blue-900 focus:ring-opacity-50 transition-colors"
              value={saldo}
              onChange={(e) => setSaldo(e.target.value)}
              required
              min="0"
            />
          </div>
          {/* Saldo display */}
          <div className="mb-2 text-center">
            {selectedUser && (
              <p className="text-base">
                <span className="font-medium text-gray-600 dark:text-gray-300">Saldo: </span>
                <b className="text-gray-900 dark:text-white">{toRupiah(getSelectedUserSaldo())}</b>
                {saldo && !isNaN(parseFloat(saldo)) && (
                  <>
                    <span className="mx-2 text-blue-700 dark:text-blue-300">
                      <i className="fa fa-arrow-right"></i>
                    </span>
                    <b className="text-blue-700 dark:text-blue-300">{toRupiah(getNewSaldo())}</b>
                  </>
                )}
              </p>
            )}
          </div>
          {error && <div className="mb-2 text-center text-red-600 dark:text-red-400 text-sm font-medium">{error}</div>}
          <button
            type="submit"
            className="w-full flex items-center justify-center gap-2 px-4 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded-md hover:bg-blue-700 dark:hover:bg-blue-800 transition-colors font-semibold"
            disabled={users.length === 0 || !selectedUser}>
            <i className="fa fa-plus"></i> Add Saldo
          </button>
        </form>
      </div>
    </div>
  );
}
