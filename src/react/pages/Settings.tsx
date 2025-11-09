import React, { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { createUrl } from '../utils/url';
import axios from 'axios';
import { getUserInfo } from '../utils/user';

// Settings page for user profile management
// Backends:
// - /php_backend/user-info.php

const Settings = () => {
  const { t } = useTranslation();
  const [username, setUsername] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [isAuthenticated, setIsAuthenticated] = useState<boolean>(false);
  const [isLoading, setIsLoading] = useState<boolean>(true);

  React.useEffect(() => {
    let mounted = true;
    (async () => {
      try {
        const data = await getUserInfo();
        if (!mounted) return;
        if ((data as any).error || !data.authenticated) {
          window.location.href = '/login';
        } else {
          setIsAuthenticated(true);
          setUsername(data.username || '');
          setEmail(data.email || '');
        }
      } catch (_err) {
        window.location.href = '/login';
      } finally {
        if (mounted) setIsLoading(false);
      }
    })();
    return () => {
      mounted = false;
    };
  }, [t]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');
    setSuccess('');
    if (!username || !email) {
      setError(t('settings_fill_all_fields'));
      return;
    }

    try {
      const payload: Record<string, string> = {
        update: '1',
        username,
        email
      };
      if (password) {
        payload.password = password;
      }

      const response = await axios.post(createUrl('/php_backend/user-info.php'), payload);
      const data = response.data;
      if (data && data.success) {
        setSuccess(t('settings_profile_updated'));
        setPassword(''); // clear password field after update
      } else {
        setError(t('settings_failed_update_profile'));
      }
    } catch (err: any) {
      setError(err.message || t('settings_failed_update_profile'));
    }
  };

  const handleBuySaldo = () => {
    // TODO: Integrate with payment/credit system
    alert(t('settings_redirect_buy_saldo'));
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <div className="animate-spin rounded-full h-32 w-32 border-b-2 border-blue-600"></div>
      </div>
    );
  }

  if (!isAuthenticated) {
    return null;
  }

  return (
    <>
      <div className="min-h-screen flex items-center justify-center bg-gray-100 dark:bg-gray-900 mt-4">
        <form onSubmit={handleSubmit} className="bg-white dark:bg-gray-800 p-8 rounded shadow-md w-full max-w-md">
          <h2 className="text-2xl font-bold mb-6 text-center text-blue-600 dark:text-white flex items-center justify-center">
            <span className="fa-light fa-user-gear mr-3 text-blue-500" style={{ fontSize: '1.5rem' }}></span>
            {t('settings_title')}
          </h2>
          {error && <div className="mb-4 text-red-500">{error}</div>}
          {success && <div className="mb-4 text-green-500">{success}</div>}
          <div className="mb-4">
            <label className="block mb-1 text-gray-700 dark:text-gray-200" htmlFor="username">
              {t('settings_username_label')}
            </label>
            <input
              id="username"
              type="text"
              name="username"
              value={username}
              onChange={(e) => setUsername(e.target.value)}
              className="w-full px-3 py-2 border rounded focus:outline-none focus:ring focus:border-blue-300 dark:bg-gray-700 dark:text-white"
              autoComplete="username"
            />
          </div>
          <div className="mb-4">
            <label className="block mb-1 text-gray-700 dark:text-gray-200" htmlFor="email">
              {t('settings_email_label')}
            </label>
            <input
              id="email"
              type="email"
              name="email"
              value={email}
              disabled
              className="w-full px-3 py-2 border rounded bg-gray-200 dark:bg-gray-700 dark:text-white cursor-not-allowed opacity-70"
              autoComplete="email"
            />
          </div>
          <div className="mb-6">
            <label className="block mb-1 text-gray-700 dark:text-gray-200" htmlFor="password">
              {t('settings_password_label')}
            </label>
            <div style={{ position: 'relative' }}>
              <input
                id="password"
                type={showPassword ? 'text' : 'password'}
                name="password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                className="w-full px-3 py-2 border rounded focus:outline-none focus:ring focus:border-blue-300 dark:bg-gray-700 dark:text-white"
                autoComplete="new-password"
                placeholder={t('settings_password_placeholder')}
                style={{ paddingRight: '2rem' }}
              />
              <span
                onClick={() => setShowPassword(!showPassword)}
                style={{
                  position: 'absolute',
                  right: '0.5rem',
                  top: '50%',
                  transform: 'translateY(-50%)',
                  cursor: 'pointer',
                  color: '#888',
                  zIndex: 2
                }}>
                <i
                  className={showPassword ? 'fa fa-eye-slash' : 'fa fa-eye'}
                  aria-hidden="true"
                  style={{ fontSize: '1.2rem' }}></i>
              </span>
            </div>
          </div>
          <button
            type="submit"
            className="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded focus:outline-none focus:ring mb-4">
            {t('settings_save_changes')}
          </button>
          <button
            type="button"
            onClick={handleBuySaldo}
            className="w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-4 rounded focus:outline-none focus:ring">
            {t('settings_buy_saldo')}
          </button>
        </form>
      </div>
    </>
  );
};

export default Settings;
