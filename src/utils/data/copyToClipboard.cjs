/**
 * Copies a string to the clipboard. Must be called from within an
 * event handler such as click. May return false if it failed, but
 * this is not always possible. Browser support for Chrome 43+,
 * Firefox 42+, Safari 10+, Edge and Internet Explorer 10+.
 * Internet Explorer: The clipboard feature may be disabled by
 * an administrator. By default, a prompt is shown the first
 * time the clipboard is used (per session).
 * @param {string} text - The text to be copied to the clipboard.
 * @param {function(string):void} [callback] - Optional callback for user feedback.
 * @returns {boolean|Promise<boolean>} - Returns true/Promise resolving to true if the operation succeeds, otherwise false.
 */
function copyToClipboard(text, callback) {
  try {
    if (navigator.clipboard) {
      return navigator.clipboard
        .writeText(text)
        .then(() => true)
        .catch((err) => {
          if (typeof callback === 'function') callback('Error copying to clipboard: ' + err);
          return false;
        });
    } else if (window.clipboardData && window.clipboardData.setData) {
      return window.clipboardData.setData('Text', text);
    } else if (document.queryCommandSupported && document.queryCommandSupported('copy')) {
      const textarea = document.createElement('textarea');
      textarea.textContent = text;
      textarea.style.position = 'fixed';
      document.body.appendChild(textarea);
      textarea.select();
      try {
        return document.execCommand('copy');
      } catch (ex) {
        if (typeof callback === 'function') callback('Copy to clipboard failed. ' + ex);
        return false;
      } finally {
        document.body.removeChild(textarea);
      }
    } else {
      if (typeof callback === 'function') callback('Copying to clipboard not supported.');
      return false;
    }
  } catch (err) {
    if (typeof callback === 'function') callback('Error copying to clipboard: ' + err);
    return false;
  }
}

if (typeof window !== 'undefined') {
  // Attach as a global fallback
  window.copyToClipboard = copyToClipboard;
}

// Prefer CommonJS export. If not present, attach to window for browser usage.
if (typeof module !== 'undefined' && typeof module.exports !== 'undefined') {
  module.exports = { copyToClipboard };
}
