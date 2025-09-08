import React, { useState } from 'react';
import { Trans, useTranslation } from 'react-i18next';
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
  const { t } = useTranslation();

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
        {t('set_point_label')}
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
        <Trans i18nKey="set_point_placeholder" values={{ exampleRupiah: 100000 }} components={[<b key="b" />]} />
      </p>
      <span className="text-xs text-gray-500 dark:text-gray-400">
        {t('current_saldo') + ' '}
        <b className="text-gray-900 dark:text-white">{currentSaldo}</b>
        {'. '}
        {newSaldo && !isNaN(parseFloat(newSaldo)) ? (
          <Trans
            i18nKey="saldo_will_change"
            values={{ newSaldo: parseFloat(newSaldo) }}
            components={[<b className="text-blue-700 dark:text-blue-300" key="b" />]}
          />
        ) : (
          t('saldo_input_hint')
        )}
      </span>
      <button
        type="button"
        className="mt-2 px-3 py-1 bg-green-600 dark:bg-green-700 text-white rounded-md hover:bg-green-700 dark:hover:bg-green-800 transition-colors text-sm font-semibold w-full flex items-center justify-center gap-2"
        disabled={loading || !userId || !newSaldo}
        onClick={handleSetSaldo}>
        {loading ? t('saving') : t('set_point_button')}
      </button>
      {err && <div className="text-xs text-red-500 dark:text-red-400">{t('set_point_error')}</div>}
    </div>
  );
};

export default EditSaldoForm;
