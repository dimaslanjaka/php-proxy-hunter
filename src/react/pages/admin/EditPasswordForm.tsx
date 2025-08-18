import React, { useState } from 'react';
import axios from 'axios';
import { useTranslation } from 'react-i18next';
import { createUrl } from '../../utils/url';

export type EditPasswordFormProps = {
  userId: string | null;
  onSuccess: () => void;
};

const EditPasswordForm: React.FC<EditPasswordFormProps> = ({ userId, onSuccess }) => {
  const [newPassword, setNewPassword] = useState('');
  const [loading, setLoading] = useState(false);
  const [err, setErr] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const { t } = useTranslation();

  const handleSetPassword = async () => {
    setErr(null);
    setSuccess(null);
    if (!userId || !newPassword) return;
    setLoading(true);
    try {
      const payload: Record<string, string> = {
        update: '1',
        username: userId,
        password: newPassword
      };
      // Use the same endpoint and logic as Settings.tsx
      const response = await axios.post(createUrl('/php_backend/user-info.php'), payload);
      const data = response.data;
      if (data && data.success) {
        setSuccess(t('set_password_success'));
        setNewPassword('');
        onSuccess();
      } else {
        setErr(data?.error || t('set_password_error'));
      }
    } catch (error: any) {
      setErr(error?.message || t('set_password_error'));
    } finally {
      setLoading(false);
    }
  };

  return (
    <div>
      <label htmlFor="edit-password" className="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-200">
        {t('edit_password_label')}
      </label>
      <input
        id="edit-password"
        type="password"
        className="block w-full rounded-md border-gray-300 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 dark:focus:border-blue-400 dark:focus:ring-blue-900 focus:ring-opacity-50 transition-colors"
        value={newPassword}
        onChange={(e) => setNewPassword(e.target.value)}
        minLength={6}
        placeholder={t('password_placeholder')}
        disabled={loading || !userId}
        autoComplete="off"
      />
      {newPassword && newPassword.length < 6 && (
        <div className="text-xs text-yellow-600 dark:text-yellow-400 mt-1">{t('password_min_length')}</div>
      )}
      <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">{t('enter_new_password_hint')}</p>
      <button
        type="button"
        className="mt-2 px-3 py-1 bg-green-600 dark:bg-green-700 text-white rounded-md hover:bg-green-700 dark:hover:bg-green-800 transition-colors text-sm font-semibold w-full flex items-center justify-center gap-2"
        disabled={loading || !userId || !newPassword || newPassword.length < 6}
        onClick={handleSetPassword}>
        {loading ? t('saving') : t('set_password_button')}
      </button>
      {err && <div className="text-xs text-red-500 dark:text-red-400">{err}</div>}
      {success && <div className="text-xs text-green-600 dark:text-green-400">{success}</div>}
    </div>
  );
};

export default EditPasswordForm;
