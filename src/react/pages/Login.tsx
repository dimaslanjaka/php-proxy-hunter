import React, { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { createUrl } from '../utils/url';
import { useNavigate } from 'react-router-dom';
import axios from 'axios';

const Login = () => {
  const { t } = useTranslation();
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [error, setError] = useState('');
  const [googleAuthUrl, setGoogleAuthUrl] = useState('');
  const indicators = {} as Record<string, any>; // Placeholder for indicators object
  const navigate = useNavigate();

  useEffect(() => {
    // Get google oauth redirect URL from environment variable
    const googleRedirectUrl = import.meta.env.VITE_GOOGLE_REDIRECT_URL || createUrl('/oauth/google');
    if (!indicators.getAuthUrl) {
      indicators.getAuthUrl = true; // Mark as done
      axios
        .get(createUrl('/php_backend/google-oauth.php', { redirect_uri: googleRedirectUrl, 'google-auth-uri': true }))
        .then((res) => {
          setGoogleAuthUrl(res.data.auth_uri);
          console.log(res.data);
          // Enable Google login button
          document.querySelector('#google-login-button')?.removeAttribute('disabled');
        })
        .catch((e) => {
          console.error('Error fetching Google Auth URL:', e);
          // setError('Failed to load Google login. Please try again later.');
          indicators.getAuthUrl = false; // Reset indicator on error
          // Disable Google login button
          document.querySelector('#google-login-button')?.setAttribute('disabled', 'true');
        });
    }
  }, [setGoogleAuthUrl]);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    // TODO: Implement authentication logic here
    if (!username || !password) {
      setError('Please enter both username and password.');
      return;
    }
    const url = createUrl('/php_backend/login.php');
    axios
      .post(url, { username, password })
      .then((response) => {
        const data = response.data;
        if (data.success) {
          navigate('/dashboard');
        } else {
          setError(data.message || 'Login failed. Please try again.');
        }
      })
      .catch((error) => {
        console.error('Login error:', error);
        setError(
          error.response?.data?.message || error.message || 'An unexpected error occurred. Please try again later.'
        );
      });
  };

  const handleGoogleLogin = () => {
    if (googleAuthUrl) {
      // window.open(googleAuthUrl, 'google', 'noopener,noreferrer')?.focus();
      window.location.href = googleAuthUrl; // Redirect to Google OAuth
    } else {
      alert(t('google_login_unavailable'));
    }
  };

  return (
    <>
      <div className="min-h-screen flex items-center justify-center bg-gray-100 dark:bg-gray-900">
        <form onSubmit={handleSubmit} className="bg-white dark:bg-gray-800 p-8 rounded shadow-md w-full max-w-md">
          <h2 className="text-2xl font-bold mb-6 text-center text-blue-600 dark:text-white">Login</h2>
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
            <div style={{ position: 'relative' }}>
              <input
                id="password"
                type={showPassword ? 'text' : 'password'}
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                className="w-full px-3 py-2 border rounded focus:outline-none focus:ring focus:border-blue-300 dark:bg-gray-700 dark:text-white"
                autoComplete="current-password"
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
            Login
          </button>
          <button
            id="google-login-button"
            disabled={!googleAuthUrl}
            aria-label="Login with Google"
            title="Login with Google"
            type="button"
            className="w-full flex items-center justify-center bg-white border border-gray-300 hover:bg-gray-100 text-gray-700 font-semibold py-2 px-4 rounded focus:outline-none focus:ring dark:bg-gray-700 dark:text-white dark:border-gray-500 dark:hover:bg-gray-600"
            style={{ marginTop: '8px' }}
            onClick={handleGoogleLogin}>
            {/* Uncomment the following line if Font Awesome Pro is set up in your project */}
            {/* <FontAwesomeIcon icon={faGoogle} className="w-5 h-5 mr-2" /> */}
            <span className="w-5 h-5 mr-2 fa-brands fa-google" style={{ fontSize: '1.25rem' }}></span>
            Login with Google
          </button>
        </form>
      </div>
    </>
  );
};

export default Login;
