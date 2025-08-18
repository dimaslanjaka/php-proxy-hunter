import React, { useState } from 'react';
import { toRupiah } from '../../../utils/number';
import { setSaldoToUser as setSaldoToUserApi } from '../../utils/user';

export type EditSaldoFormProps = {
  userId: string | null;
  currentSaldo: number;
  onSuccess: () => void;
};

const setSaldoToUser = async (userId: string, saldo: number) => {
  return setSaldoToUserApi(userId, saldo);
};

const EditSaldoForm: React.FC<EditSaldoFormProps> = ({ userId, currentSaldo, onSuccess }) => {
  const [newSaldo, setNewSaldo] = useState('');
  const [loading, setLoading] = useState(false);
  const [err, setErr] = useState<string | null>(null);

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
    <div>
      <label htmlFor="edit-saldo" className="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-200">
        Set Saldo Baru
      </label>
      <input
        id="edit-saldo"
        type="number"
        className="block w-full rounded-md border-gray-300 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 dark:focus:border-blue-400 dark:focus:ring-blue-900 focus:ring-opacity-50 transition-colors"
        value={newSaldo}
        onChange={(e) => setNewSaldo(e.target.value)}
        min="0"
        placeholder={currentSaldo.toString()}
        disabled={loading || !userId}
        autoComplete="off"
      />
      <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
        Masukkan saldo baru (akan menggantikan saldo lama). Contoh: <b>100000</b> (akan menjadi {toRupiah(100000)})
      </p>
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
        className="mt-2 px-3 py-1 bg-green-600 dark:bg-green-700 text-white rounded-md hover:bg-green-700 dark:hover:bg-green-800 transition-colors text-sm font-semibold w-full flex items-center justify-center gap-2"
        disabled={loading || !userId || !newSaldo}
        onClick={handleSetSaldo}>
        {loading ? 'Saving...' : 'Set Saldo'}
      </button>
      {err && <div className="text-xs text-red-500 dark:text-red-400">{err}</div>}
    </div>
  );
};

export default EditSaldoForm;
