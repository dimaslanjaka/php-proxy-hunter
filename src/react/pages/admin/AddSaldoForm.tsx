import React, { useState } from 'react';
import { toRupiah } from '../../../utils/number';
import { addSaldoToUser } from '../../utils/user';

export type AddSaldoFormProps = {
  userId: string | null;
  currentSaldo: number;
  onSuccess: () => void;
};

const AddSaldoForm: React.FC<AddSaldoFormProps> = ({ userId, currentSaldo, onSuccess }) => {
  const [saldo, setSaldo] = useState('');
  const [loading, setLoading] = useState(false);
  const [err, setErr] = useState<string | null>(null);

  return (
    <div>
      <label htmlFor="saldo" className="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-200">
        Tambah Saldo
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
        autoComplete="off"
      />
      <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
        Masukkan jumlah saldo yang ingin <b>ditambahkan</b> ke user yang dipilih.
        <br />
        Contoh: <b>100000</b> (saldo akan bertambah).
      </p>
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

export default AddSaldoForm;
