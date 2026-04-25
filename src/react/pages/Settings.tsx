import React, { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { createUrl } from '../utils/url';
import axios from 'axios';
import { startRegistration } from '@simplewebauthn/browser';
import { getUserInfo } from '../utils/user';
import { useSnackbar } from '../components/Snackbar';

// Settings page for user profile management
// Backends:
// - /php_backend/user-info.php

const Settings = () => {
  const { t } = useTranslation();
  const { showSnackbar } = useSnackbar();
  const [username, setUsername] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [token, setToken] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [isAuthenticated, setIsAuthenticated] = useState<boolean>(false);
  const [isLoading, setIsLoading] = useState<boolean>(true);
  const [credentials, setCredentials] = useState<Array<any>>([]);
  const [loadingCredentials, setLoadingCredentials] = useState<boolean>(false);
  const [showRemoveModal, setShowRemoveModal] = useState<boolean>(false);
  const [selectedCredentialId, setSelectedCredentialId] = useState<string | null>(null);

  const fetchCredentials = async () => {
    setLoadingCredentials(true);
    try {
      const res = await axios.get(createUrl('/php_backend/webauthn/list_credentials.php'));
      if (res.data && res.data.error === false) {
        setCredentials(res.data.credentials || []);
      } else {
        setCredentials([]);
      }
    } catch (_e) {
      setCredentials([]);
    } finally {
      setLoadingCredentials(false);
    }
  };

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
          setToken(data.token || '');
          // fetch registered passkeys for this user
          fetchCredentials();
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

  const handleWebAuthnRegister = async () => {
    setError('');
    setSuccess('');

    try {
      const url = createUrl('/php_backend/webauthn/register_options.php');
      // Server uses session authenticated email; no username/email should be sent from client.
      const res = await axios.post(url);
      if (res.data && res.data.error === false && res.data.publicKey) {
        const credential = await startRegistration(res.data.publicKey);
        const verifyUrl = createUrl('/php_backend/webauthn/register_verify.php');
        const verifyRes = await axios.post(verifyUrl, { credential });
        if (verifyRes.data && verifyRes.data.error === false) {
          setSuccess(t('settings_webauthn_registered') || 'Security key registered');
          // refresh list
          fetchCredentials();
        } else {
          setError(verifyRes.data?.message || 'Registration failed');
        }
      }
    } catch (e: any) {
      console.error('WebAuthn register error', e);
      setError(e?.response?.data?.message || e.message || 'WebAuthn register failed');
    }
  };

  const requestRemoveCredential = (credential_id: string) => {
    setSelectedCredentialId(credential_id);
    setShowRemoveModal(true);
  };

  const performRemoveCredential = async () => {
    const credential_id = selectedCredentialId;
    setShowRemoveModal(false);
    setSelectedCredentialId(null);
    if (!credential_id) return;
    try {
      const res = await axios.post(createUrl('/php_backend/webauthn/remove_credential.php'), { credential_id });
      if (res.data && res.data.error === false) {
        showSnackbar({ message: 'Removed', type: 'success' });
        fetchCredentials();
      } else {
        showSnackbar({ message: res.data?.message || 'Failed to remove', type: 'danger' });
      }
    } catch (e: any) {
      showSnackbar({ message: e?.response?.data?.message || e.message || 'Failed to remove', type: 'danger' });
    }
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <div className="animate-spin rounded-full h-32 w-32 border-b-2 border-blue-600"></div>
      </div>
    );
  }

  if (!isAuthenticated) return null;

  return (
    <div className="min-h-screen flex items-start md:items-center justify-center bg-gray-100 dark:bg-gray-900 px-4 py-8">
      <form
        onSubmit={handleSubmit}
        className="w-full max-w-4xl bg-white dark:bg-gray-800 rounded shadow-md overflow-hidden flex flex-col md:flex-row">
        <div className="p-6 md:p-12 md:w-1/2">
          <h2 className="text-2xl font-bold mb-6 text-blue-600 dark:text-white flex items-center">
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

          <div className="mb-4">
            <label className="block mb-1 text-gray-700 dark:text-gray-200" htmlFor="token">
              {t('settings_api_token_label') || 'API Token'}
            </label>
            <div className="relative flex items-center">
              <input
                id="token"
                type="text"
                value={token}
                readOnly
                className="w-full px-3 py-2 border rounded bg-gray-100 dark:bg-gray-800 dark:text-white cursor-text"
              />
              <button
                type="button"
                onClick={() => {
                  const text = token || '';
                  if (!text) return;
                  navigator.clipboard
                    ?.writeText(text)
                    .then(() => showSnackbar({ message: t('settings_copy_success') || 'Copied!', type: 'success' }))
                    .catch(() => showSnackbar({ message: t('settings_copy_failed') || 'Copy failed', type: 'danger' }));
                }}
                className="ml-2 inline-flex items-center px-3 py-2 bg-gray-200 text-gray-800 dark:bg-gray-600 dark:text-white rounded hover:bg-gray-300 dark:hover:bg-gray-500 focus:outline-none focus:ring"
                title={t('settings_copy') || 'Copy'}>
                <i className="fa-duotone fa-copy" aria-hidden="true"></i>
              </button>
            </div>
          </div>

          <div className="mb-6">
            <label className="block mb-1 text-gray-700 dark:text-gray-200" htmlFor="password">
              {t('settings_password_label')}
            </label>
            <div className="relative">
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
              <button
                type="button"
                onClick={() => setShowPassword(!showPassword)}
                aria-label={showPassword ? 'Hide password' : 'Show password'}
                className="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500">
                <i className={showPassword ? 'fa-duotone fa-eye-slash' : 'fa-duotone fa-eye'} aria-hidden="true"></i>
              </button>
            </div>
          </div>

          <div className="mt-4">
            <button
              type="submit"
              className="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded focus:outline-none focus:ring">
              {t('settings_save_changes')}
            </button>
          </div>
        </div>

        <div className="p-6 md:p-12 md:w-1/2 bg-gray-50 dark:bg-gray-700 flex flex-col items-stretch md:justify-center justify-start gap-3">
          <button
            type="button"
            onClick={handleBuySaldo}
            className="w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-4 rounded focus:outline-none focus:ring">
            <span className="mr-2">
              <i className="fa-duotone fa-wallet" aria-hidden="true"></i>
            </span>
            {t('settings_buy_saldo')}
          </button>

          <button
            type="button"
            onClick={handleWebAuthnRegister}
            className="w-full flex items-center justify-center bg-purple-600 hover:bg-purple-700 text-white font-semibold py-2 px-4 rounded focus:outline-none focus:ring">
            <span className="mr-2">
              <i className="fa-duotone fa-key" aria-hidden="true"></i>
            </span>
            {t('settings_register_webauthn')}
          </button>
          <div className="mt-4 w-full bg-white dark:bg-gray-800 p-3 rounded shadow-inner">
            <h3 className="font-semibold mb-2 text-gray-700 dark:text-gray-200">Registered Security Keys</h3>
            {loadingCredentials ? (
              <div>{t('loading')}</div>
            ) : credentials.length === 0 ? (
              <div className="text-sm text-gray-500">{t('settings_no_passkeys')}</div>
            ) : (
              credentials.map((c) => (
                <div key={c.credential_id} className="flex items-center justify-between py-2 border-b last:border-b-0">
                  <div className="truncate mr-2 text-sm text-gray-800 dark:text-gray-100">{c.credential_id}</div>
                  <div className="flex items-center gap-2">
                    <button
                      type="button"
                      onClick={() => requestRemoveCredential(c.credential_id)}
                      className="text-sm text-red-600 hover:underline">
                      {t('delete')}
                    </button>
                  </div>
                </div>
              ))
            )}
          </div>
          {/* Modal (Flowbite style) */}
          {showRemoveModal && (
            <div
              id="deleteModal"
              tabIndex={-1}
              aria-hidden={false}
              className="fixed inset-0 z-50 flex items-center justify-center">
              <div className="fixed inset-0 bg-black/50" onClick={() => setShowRemoveModal(false)}></div>
              <div className="relative p-4 w-full max-w-md">
                <div className="relative p-4 text-center bg-white rounded-lg shadow dark:bg-gray-800 sm:p-5">
                  <button
                    type="button"
                    className="text-gray-400 absolute top-2.5 right-2.5 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm p-1.5 ml-auto inline-flex items-center dark:hover:bg-gray-600 dark:hover:text-white"
                    onClick={() => setShowRemoveModal(false)}>
                    <i className="fa-duotone fa-xmark" aria-hidden="true" style={{ fontSize: '1.25rem' }}></i>
                    <span className="sr-only">Close modal</span>
                  </button>
                  <i
                    className="fa-duotone fa-trash text-gray-400 dark:text-gray-500 text-3xl mb-3.5 mx-auto"
                    aria-hidden="true"></i>
                  <p className="mb-4 text-gray-500 dark:text-gray-300">{t('settings_remove_passkey_confirm')}</p>
                  <div className="flex justify-center items-center space-x-4">
                    <button
                      type="button"
                      onClick={() => setShowRemoveModal(false)}
                      className="py-2 px-3 text-sm font-medium text-gray-500 bg-white rounded-lg border border-gray-200 hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-primary-300 hover:text-gray-900 focus:z-10 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-500 dark:hover:text-white dark:hover:bg-gray-600 dark:focus:ring-gray-600">
                      {t('settings_remove_cancel')}
                    </button>
                    <button
                      type="button"
                      onClick={() => performRemoveCredential()}
                      className="py-2 px-3 text-sm font-medium text-center text-white bg-red-600 rounded-lg hover:bg-red-700 focus:ring-4 focus:outline-none focus:ring-red-300 dark:bg-red-500 dark:hover:bg-red-600 dark:focus:ring-red-900">
                      {t('settings_remove_confirm')}
                    </button>
                  </div>
                </div>
              </div>
            </div>
          )}
        </div>
      </form>
    </div>
  );
};

export default Settings;
