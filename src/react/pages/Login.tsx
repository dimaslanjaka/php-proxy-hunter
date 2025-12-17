import React, { useEffect, useState } from 'react';
import { createUrl } from '../utils/url';
import { useNavigate } from 'react-router-dom';
import axios from 'axios';
import { startAuthentication } from '@simplewebauthn/browser';
import FacebookLogin from 'react-facebook-login';

const Login: React.FC = () => {
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [error, setError] = useState('');
  const [googleAuthUrl, setGoogleAuthUrl] = useState('');
  const indicators = {} as Record<string, any>;
  const navigate = useNavigate();

  useEffect(() => {
    const googleRedirectUrl = import.meta.env.VITE_GOOGLE_REDIRECT_URL || createUrl('/oauth/google');
    if (!indicators.getAuthUrl) {
      indicators.getAuthUrl = true;
      axios
        .get(createUrl('/php_backend/google-oauth.php', { redirect_uri: googleRedirectUrl, 'google-auth-uri': true }))
        .then((res) => setGoogleAuthUrl(res.data.auth_uri))
        .catch(() => setGoogleAuthUrl(''));
    }
  }, []);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!username || !password) {
      setError('Please enter both username and password.');
      return;
    }
    const url = createUrl('/php_backend/login.php');
    axios
      .post(url, { username, password })
      .then((response) => {
        const data = response.data;
        if (data.success) navigate('/dashboard');
        else setError(data.message || 'Login failed. Please try again.');
      })
      .catch((err) => setError(err.response?.data?.message || err.message || 'An unexpected error occurred.'));
  };

  const handleGoogleLogin = () => {
    if (googleAuthUrl) window.location.href = googleAuthUrl;
    else alert('Google login unavailable');
  };

  const handleWebAuthnLogin = async () => {
    try {
      const url = createUrl('/php_backend/webauthn/auth_options.php');
      const res = await axios.post(url, { username });
      if (res.data && res.data.error === false && res.data.publicKey) {
        const assertion = await startAuthentication(res.data.publicKey);
        const verifyUrl = createUrl('/php_backend/webauthn/auth_verify.php');
        const verifyRes = await axios.post(verifyUrl, { username, assertion });
        if (verifyRes.data && verifyRes.data.error === false) navigate('/dashboard');
        else setError(verifyRes.data?.message || 'Authentication failed');
      }
    } catch (e: any) {
      setError(e?.response?.data?.message || e.message || 'WebAuthn login failed');
    }
  };

  const fbClicked = () => console.log('FB click');
  const fbResponse = (res: any) => console.log('FB resp', res);

  return (
    <div className="min-h-screen flex items-start md:items-center justify-center bg-gray-100 dark:bg-gray-900 px-4 py-8">
      <form
        onSubmit={handleSubmit}
        className="w-full max-w-4xl bg-white dark:bg-gray-800 rounded shadow-md overflow-hidden flex flex-col md:flex-row">
        <div className="p-6 md:p-12 md:w-1/2">
          <h2 className="text-2xl font-bold mb-6 text-blue-600 dark:text-white">Login</h2>
          {error && <div className="mb-4 text-red-500">{error}</div>}

          <div className="mb-4">
            <label className="block mb-1 text-gray-700 dark:text-gray-200" htmlFor="username">
              Username
            </label>
            <input
              id="username"
              type="text"
              value={username}
              onChange={(e) => setUsername(e.target.value)}
              className="w-full px-3 py-2 border rounded focus:outline-none focus:ring focus:border-blue-300 dark:bg-gray-700 dark:text-white"
              autoComplete="username"
            />
          </div>

          <div className="mb-6">
            <label className="block mb-1 text-gray-700 dark:text-gray-200" htmlFor="password">
              Password
            </label>
            <div className="relative">
              <input
                id="password"
                type={showPassword ? 'text' : 'password'}
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                className="w-full px-3 py-2 border rounded focus:outline-none focus:ring focus:border-blue-300 dark:bg-gray-700 dark:text-white"
                autoComplete="current-password"
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
              Login
            </button>
          </div>
        </div>

        <div className="p-6 md:p-12 md:w-1/2 bg-gray-50 dark:bg-gray-700 flex flex-col items-stretch md:justify-center justify-start gap-3">
          {import.meta.env.VITE_FACEBOOK_APP_ID ? (
            <FacebookLogin
              appId={import.meta.env.VITE_FACEBOOK_APP_ID}
              autoLoad={false}
              fields="name,email"
              onClick={fbClicked}
              callback={fbResponse}
              cssClass="w-full flex items-center justify-center bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded focus:outline-none focus:ring"
              icon="fa-facebook"
              textButton="&nbsp;Login with Facebook"
            />
          ) : (
            <button
              type="button"
              disabled
              title="Facebook App ID not configured"
              className="w-full flex items-center justify-center bg-gray-300 text-gray-600 font-semibold py-2 px-4 rounded">
              <span className="w-5 h-5 mr-2 fa-brands fa-facebook" style={{ fontSize: '1.25rem' }}></span>
              Facebook login (disabled)
            </button>
          )}

          <button
            id="google-login-button"
            disabled={!googleAuthUrl}
            aria-label="Login with Google"
            title="Login with Google"
            type="button"
            className="w-full flex items-center justify-center bg-white border border-gray-300 hover:bg-gray-100 text-gray-700 font-semibold py-2 px-4 rounded focus:outline-none focus:ring dark:bg-gray-700 dark:text-white dark:border-gray-500 dark:hover:bg-gray-600"
            onClick={handleGoogleLogin}>
            <span className="w-5 h-5 mr-2 fa-brands fa-google" style={{ fontSize: '1.25rem' }}></span>
            Login with Google
          </button>

          <button
            type="button"
            onClick={handleWebAuthnLogin}
            className="w-full flex items-center justify-center bg-gray-900 hover:bg-black text-white font-semibold py-2 px-4 rounded focus:outline-none focus:ring">
            <span className="mr-2">
              <i className="fa-duotone fa-key" aria-hidden="true"></i>
            </span>
            Login with Security Key
          </button>
        </div>
      </form>
    </div>
  );
};

export default Login;
