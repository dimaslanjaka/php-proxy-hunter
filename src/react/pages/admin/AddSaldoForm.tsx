import React, { useState } from 'react';
import { useTranslation, Trans } from 'react-i18next';
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
  const { t } = useTranslation();

  return (
    <div>
      <label htmlFor="saldo" className="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-200">
        {t('add_saldo_label')}
      </label>
      <input
        id="saldo"
        type="number"
        placeholder={t('add_saldo_hint')}
        className="block w-full rounded-md border-gray-300 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 dark:focus:border-blue-400 dark:focus:ring-blue-900 focus:ring-opacity-50 transition-colors"
        value={saldo}
        onChange={(e) => setSaldo(e.target.value)}
        required
        min="0"
        disabled={loading || !userId}
        autoComplete="off"
      />
      <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
        <Trans i18nKey="add_saldo_placeholder" components={[<b key="b1" />, <b key="b2" />]} />
      </p>
      <span className="text-xs text-gray-500 dark:text-gray-400">
        {t('add_saldo_current') + ' '}
        <b className="text-gray-900 dark:text-white">{toRupiah(currentSaldo)}</b>
        {'. '}
        {saldo && !isNaN(parseFloat(saldo)) ? (
          <Trans
            i18nKey="add_saldo_will"
            values={{ newSaldo: toRupiah(currentSaldo + parseFloat(saldo)) }}
            components={[<b className="text-blue-700 dark:text-blue-300" key="b" />]}
          />
        ) : (
          t('add_saldo_hint')
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
              setErr(err?.message || t('add_saldo_error'));
            } finally {
              setLoading(false);
            }
          } else {
            setErr(t('add_saldo_select_user'));
          }
        }}>
        <i className="fa fa-plus"></i> {loading ? t('saving') : t('add_saldo_button')}
      </button>
      {err && <div className="text-xs text-red-500 dark:text-red-400">{err}</div>}
    </div>
  );
};

export default AddSaldoForm;
