/// <reference path="../../../types/window-shim.d.ts" />
import $ from 'jquery';
import { initializeLanguageSelector, updateLanguage } from './languages.js';

/**
 * dynamically get base url of current project.
 */
const project_base_url =
  !isNaN(Number(location.port)) && location.port !== '' ? `${location.hostname}:${location.port}` : location.hostname;

export const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

// Language selector (global)
initializeLanguageSelector().then(() => {
  if ($('#language-select').length) {
    $('#language-select').on('change', updateLanguage);
  }
});

// Navigation bar (global)
$.getJSON('/php_backend/user-info.php', function (data) {
  if (data.authenticated) {
    $('[data-role="login"]').hide();
    $('[data-role="logout"]').show();
  } else {
    $('[data-role="login"]').show();
    $('[data-role="logout"]').hide();
  }
});

// Google Recaptcha V3
fetch(`//${project_base_url}/info.php`)
  .then((res) => res.json())
  .then((data) => {
    const siteKey_1 = data['captcha-site-key'];
    const embedder = document.createElement('div');
    embedder.classList.add('g-recaptcha');
    embedder.setAttribute('data-sitekey', data['captcha-v2-site-key']);
    embedder.setAttribute('data-callback', 'send_token');
    embedder.setAttribute('data-action', 'submit');
    $('#recaptcha').append(embedder);
    sleep(1000).then(() => {
      const recaptchaV2Script = document.createElement('script');
      recaptchaV2Script.src = 'https://www.google.com/recaptcha/api.js';
      document.body.appendChild(recaptchaV2Script);
      const recaptchaV3Script = document.createElement('script');
      recaptchaV3Script.src = 'https://www.google.com/recaptcha/api.js?render=' + siteKey_1;
      recaptchaV3Script.onload = function () {
        grecaptcha.ready(() => {
          grecaptcha.execute(siteKey_1, { action: 'submit' }).then((token) => {
            fetch(`//${project_base_url}/php_backend/recaptcha.php`, {
              method: 'POST',
              headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
              },
              body: new URLSearchParams({
                'g-recaptcha-response': token
              }).toString()
            })
              .then((res) => res.json())
              .then((data) => {
                // Remove recaptcha element
                if (data.success) {
                  const el = document.getElementById('recaptcha');
                  if (el) el.remove();
                }
                return data;
              });
          });
        });
      };
      document.body.appendChild(recaptchaV3Script);
    });
  });

/**
 * Fetches user data and ensures the user is authenticated.
 *
 * @returns {Promise<import('../../../types/user.js').UserInfoResponse>} A promise that resolves with the user data if authenticated, or rejects with an error.
 */
export function getUserData() {
  return new Promise((resolve, reject) => {
    /**
     * Callback for handling user data response.
     *
     * @callback UserInfoCallback
     * @param {import('../../../types/user.js').UserInfoResponse} data - The user information response object.
     */

    /**
     * @type {UserInfoCallback}
     */
    const callback = function (data) {
      if (!data.authenticated) {
        location.href = '/login';
        reject(new Error('User not authenticated'));
      } else {
        resolve(data);
      }
    };

    $.getJSON('/php_backend/user-info.php', callback).fail(function () {
      reject(new Error('Failed to fetch user data'));
    });
  });
}

/**
 * Displays a snackbar message for a specified duration.
 * @param {...string|Error} messages - The messages to be displayed, which can also be an Error object.
 */
export function showSnackbar(...messages) {
  // Get the snackbar element
  const snackbar = document.getElementById('snackbar');
  if (!snackbar) {
    throw new Error('Snackbar element not found');
  }

  // Combine all messages into one string
  // Set the message
  snackbar.textContent = messages
    .map((msg) => {
      if (msg instanceof Error) {
        // If message is an Error object, extract the error message
        return `Error: ${msg.message}`;
      } else if (typeof msg !== 'string') {
        // If message is not a string, stringify it
        return JSON.stringify(msg);
      } else {
        return msg;
      }
    })
    .join(' ');

  // Add the "show" class to DIV
  snackbar.classList.add('show');

  // Hide the snackbar after 3 seconds
  setTimeout(function () {
    snackbar.classList.remove('show');
  }, 3000);
}

/**
 * Copies a string to the clipboard. Must be called from within an
 * event handler such as click. Returns a promise that resolves to true
 * if the operation succeeds, otherwise resolves to false.
 *
 * Browser support for Chrome 43+, Firefox 42+, Safari 10+, Edge and IE 10+.
 *
 * @param {string} text - The text to be copied to the clipboard.
 * @returns {Promise<boolean>} - Resolves to true if copy succeeded, false otherwise.
 */
export function copyToClipboard(text) {
  return new Promise((resolve) => {
    try {
      if (navigator.clipboard) {
        navigator.clipboard
          .writeText(text)
          .then(() => resolve(true))
          .catch((err) => {
            showSnackbar('Error copying to clipboard:', err);
            resolve(false);
          });
      } else if (window.clipboardData && window.clipboardData.setData) {
        // For IE
        window.clipboardData.setData('Text', text);
        resolve(true);
      } else if (document.queryCommandSupported && document.queryCommandSupported('copy')) {
        const textarea = document.createElement('textarea');
        textarea.textContent = text;
        textarea.style.position = 'fixed'; // Avoid scrolling
        document.body.appendChild(textarea);
        textarea.select();
        try {
          const success = document.execCommand('copy');
          if (!success) {
            showSnackbar('Copy command was not successful.');
          }
          resolve(success);
        } catch (ex) {
          showSnackbar('Copy to clipboard failed.', ex);
          resolve(false);
        } finally {
          document.body.removeChild(textarea);
        }
      } else {
        showSnackbar('Copying to clipboard not supported.');
        resolve(false);
      }
    } catch (err) {
      showSnackbar('Error copying to clipboard:', err);
      resolve(false);
    }
  });
}
