import React, { useState } from 'react';
import { createUrl } from '../utils/url';

import { fetchUserInfo } from '../utils/user';

// Settings page for user profile management
// Backends:
// - /php_backend/user-info.php

const Settings = () => {
  const [username, setUsername] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');

  React.useEffect(() => {
    fetchUserInfo()
      .then((data) => {
        if (data.authenticated) {
          setUsername(data.username || '');
          setEmail(data.email || '');
        } else {
          setError('You must be logged in to access settings.');
        }
      })
      .catch((err) => {
        console.error('Error fetching user info:', err);
        setError('Failed to load user information.');
      });
  }, []);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');
    setSuccess('');
    if (!username || !email) {
      setError('Please fill in all fields.');
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

      const response = await fetch(createUrl('/php_backend/user-info.php'), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(payload)
        // credentials: 'include'
      });
      if (!response.ok) {
        throw new Error('Failed to update user info');
      }
      const data = await response.json();
      if (data && data.authenticated) {
        setSuccess('Profile updated successfully!');
        setPassword(''); // clear password field after update
      } else {
        setError('Failed to update profile.');
      }
    } catch (err: any) {
      setError(err.message || 'Failed to update profile.');
    }
  };

  const handleBuySaldo = () => {
    // TODO: Integrate with payment/credit system
    alert('Redirecting to buy saldo (credit)...');
  };

  return (
    <>
      <div className="min-h-screen flex items-center justify-center bg-gray-100 dark:bg-gray-900">
        <form onSubmit={handleSubmit} className="bg-white dark:bg-gray-800 p-8 rounded shadow-md w-full max-w-md">
          <h2 className="text-2xl font-bold mb-6 text-center text-blue-600 dark:text-white flex items-center justify-center">
            <span className="fa-light fa-user-gear mr-3 text-blue-500" style={{ fontSize: '1.5rem' }}></span>
            User Settings
          </h2>
          {error && <div className="mb-4 text-red-500">{error}</div>}
          {success && <div className="mb-4 text-green-500">{success}</div>}
          <div className="mb-4">
            <label className="block mb-1 text-gray-700 dark:text-gray-200" htmlFor="username">
              Username
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
              Email
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
              New Password
            </label>
            <input
              id="password"
              type="password"
              name="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              className="w-full px-3 py-2 border rounded focus:outline-none focus:ring focus:border-blue-300 dark:bg-gray-700 dark:text-white"
              autoComplete="new-password"
              placeholder="Leave blank to keep current password"
            />
          </div>
          <button
            type="submit"
            className="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded focus:outline-none focus:ring mb-4">
            Save Changes
          </button>
          <button
            type="button"
            onClick={handleBuySaldo}
            className="w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-4 rounded focus:outline-none focus:ring">
            Buy Saldo (Credit)
          </button>
        </form>
      </div>
    </>
  );
};

export default Settings;
