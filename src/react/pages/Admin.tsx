import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { addSaldoToUser, getListOfUsers, getUserInfo } from '../utils/user';
import { toRupiah } from '../../utils/number';
import { setSaldoToUser as setSaldoToUserApi } from '../utils/user';

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
      <div className="w-full bg-white dark:bg-gray-800 rounded-lg shadow-lg p-8 transition-colors">
        <h1 className="text-2xl font-bold mb-6 text-center flex items-center justify-center gap-2 text-blue-700 dark:text-blue-300">
          <i className="text-green-500 dark:text-green-400 fa fa-money-bill-wave"></i>
          Saldo Manager
        </h1>
        <form onSubmit={handleSubmit} className="space-y-6">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            {/* Left column: User select + AddSaldoForm */}
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
              <div className="mt-4">
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
            </div>
            {/* Right column: EditSaldoForm */}
            <div>
              <label htmlFor="edit-saldo" className="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-200">
                Edit Saldo (Set Exact Value)
              </label>
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
              <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Masukkan saldo baru (akan menggantikan saldo lama). Contoh: <b>100000</b> (akan menjadi{' '}
                {toRupiah(100000)})
              </p>
            </div>
          </div>
          {/* Saldo display removed as requested; text helpers remain in each input section */}
          {error && <div className="mb-2 text-center text-red-600 dark:text-red-400 text-sm font-medium">{error}</div>}
          {/* The main form submit button is removed; each action now has its own button */}
        </form>
      </div>
    </div>
  );
}

// EditSaldoForm component for setting saldo directly
type EditSaldoFormProps = {
  userId: string | null;
  currentSaldo: number;
  onSuccess: () => void;
};

// Set saldo to exact value for a user
const setSaldoToUser = async (userId: string, saldo: number) => {
  // Call backend API to set saldo (not add)
  return setSaldoToUserApi(userId, saldo);
};

const EditSaldoForm: React.FC<EditSaldoFormProps> = ({ userId, currentSaldo, onSuccess }) => {
  const [newSaldo, setNewSaldo] = useState('');
  const [loading, setLoading] = useState(false);
  const [err, setErr] = useState<string | null>(null);

  // Helper to format Rupiah
  const formatRupiah = (val: string) => {
    const n = parseFloat(val);
    if (isNaN(n)) return '';
    return toRupiah(n);
  };

  const handleSetSaldo = async () => {
    setErr(null);
    if (!userId) return;
    setLoading(true);
    try {
      await setSaldoToUser(userId, parseFloat(newSaldo));
      setNewSaldo('');
      onSuccess();
    } catch (error: any) {
      setErr(error?.message || 'Failed to set saldo.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="flex flex-col gap-2">
      <input
        id="edit-saldo"
        type="number"
        className="block w-full rounded-md border-gray-300 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 dark:focus:border-blue-400 dark:focus:ring-blue-900 focus:ring-opacity-50 transition-colors"
        value={newSaldo}
        onChange={(e) => setNewSaldo(e.target.value)}
        min="0"
        placeholder={currentSaldo.toString()}
        disabled={loading || !userId}
      />
      <span className="text-xs text-gray-500 dark:text-gray-400">
        {`Saldo saat ini: `}
        <b className="text-gray-900 dark:text-white">{toRupiah(currentSaldo)}</b>
        {'. '}
        {newSaldo && !isNaN(parseFloat(newSaldo)) ? (
          <>
            Saldo akan diubah menjadi <b className="text-blue-700 dark:text-blue-300">{formatRupiah(newSaldo)}</b>
          </>
        ) : (
          'Masukkan saldo baru (dalam angka)'
        )}
      </span>
      <button
        type="button"
        className="px-3 py-1 bg-yellow-500 dark:bg-yellow-600 text-white rounded-md hover:bg-yellow-600 dark:hover:bg-yellow-700 transition-colors text-sm font-semibold"
        disabled={loading || !userId || !newSaldo}
        onClick={handleSetSaldo}>
        {loading ? 'Saving...' : 'Set Saldo'}
      </button>
      {err && <div className="text-xs text-red-500 dark:text-red-400">{err}</div>}
    </div>
  );
};

// AddSaldoForm component for adding saldo (modularized like EditSaldoForm)
type AddSaldoFormProps = {
  userId: string | null;
  currentSaldo: number;
  onSuccess: () => void;
};

const AddSaldoForm: React.FC<AddSaldoFormProps> = ({ userId, currentSaldo, onSuccess }) => {
  const [saldo, setSaldo] = useState('');
  const [loading, setLoading] = useState(false);
  const [err, setErr] = useState<string | null>(null);

  return (
    <div className="flex flex-col gap-2">
      <label htmlFor="saldo" className="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-200">
        Add Saldo Amount
      </label>
      <input
        id="saldo"
        type="number"
        placeholder="Masukkan jumlah saldo (angka)"
        className="block w-full rounded-md border-gray-300 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 dark:focus:border-blue-400 dark:focus:ring-blue-900 focus:ring-opacity-50 transition-colors"
        value={saldo}
        onChange={(e) => setSaldo(e.target.value)}
        required
        min="0"
        disabled={loading || !userId}
      />
      <span className="text-xs text-gray-500 dark:text-gray-400">
        {`Saldo saat ini: `}
        <b className="text-gray-900 dark:text-white">{toRupiah(currentSaldo)}</b>
        {'. '}
        {saldo && !isNaN(parseFloat(saldo)) ? (
          <>
            Saldo akan bertambah menjadi{' '}
            <b className="text-blue-700 dark:text-blue-300">{toRupiah(currentSaldo + parseFloat(saldo))}</b>
          </>
        ) : (
          'Masukkan jumlah saldo yang ingin ditambahkan (dalam angka)'
        )}
      </span>
      <button
        type="button"
        className="mt-2 px-3 py-1 bg-green-600 dark:bg-green-700 text-white rounded-md hover:bg-green-700 dark:hover:bg-green-800 transition-colors text-sm font-semibold w-full flex items-center justify-center gap-2"
        disabled={loading || !userId || !saldo || isNaN(parseFloat(saldo))}
        onClick={async () => {
          setErr(null);
          if (userId) {
            setLoading(true);
            try {
              await addSaldoToUser(userId, parseFloat(saldo));
              onSuccess();
              setSaldo('');
            } catch (err: any) {
              setErr(err?.message || 'Failed to add saldo.');
            } finally {
              setLoading(false);
            }
          } else {
            setErr('Please select a user to add saldo.');
          }
        }}>
        <i className="fa fa-plus"></i> {loading ? 'Saving...' : 'Add Saldo'}
      </button>
      {err && <div className="text-xs text-red-500 dark:text-red-400">{err}</div>}
    </div>
  );
};
