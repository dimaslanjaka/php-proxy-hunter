import $ from 'jquery';
import { updateLanguage } from './languages.js';

// Language selector
if ($('#language-select').length) {
  $('#language-select').on('change', updateLanguage);
}

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
 * event handler such as click. May return false if it failed, but
 * this is not always possible. Browser support for Chrome 43+,
 * Firefox 42+, Safari 10+, Edge and Internet Explorer 10+.
 * Internet Explorer: The clipboard feature may be disabled by
 * an administrator. By default, a prompt is shown the first
 * time the clipboard is used (per session).
 * @param {string} text - The text to be copied to the clipboard.
 * @returns {boolean} - Returns true if the operation succeeds, otherwise returns false.
 */
export function copyToClipboard(text) {
  try {
    if (navigator.clipboard) {
      return navigator.clipboard
        .writeText(text)
        .then(() => true)
        .catch((err) => {
          showSnackbar('Error copying to clipboard:', err);
          return false;
        });
    } else if (window.clipboardData && window.clipboardData.setData) {
      // Internet Explorer-specific code path to prevent textarea being shown while dialog is visible.
      return window.clipboardData.setData('Text', text);
    } else if (document.queryCommandSupported && document.queryCommandSupported('copy')) {
      const textarea = document.createElement('textarea');
      textarea.textContent = text;
      textarea.style.position = 'fixed'; // Prevent scrolling to bottom of page in Microsoft Edge.
      document.body.appendChild(textarea);
      textarea.select();
      try {
        return document.execCommand('copy'); // Security exception may be thrown by some browsers.
      } catch (ex) {
        showSnackbar('Copy to clipboard failed.', ex);
        return false;
      } finally {
        document.body.removeChild(textarea);
      }
    } else {
      showSnackbar('Copying to clipboard not supported.');
      return false;
    }
  } catch (err) {
    showSnackbar('Error copying to clipboard:', err);
    return false;
  }
}
