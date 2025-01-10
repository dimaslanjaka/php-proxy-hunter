import axios from 'axios';

/**
 * Checks if a given URL is valid.
 *
 * @param {string} urlString - The URL string to validate.
 * @returns {boolean} - Returns true if the URL is valid, false otherwise.
 */
export function isValidUrl(urlString) {
  try {
    new URL(urlString);
    return true; // URL is valid
  } catch (_) {
    return false; // URL is invalid
  }
}

/**
 * Filter working URLs from an array.
 * @param {string[]} urls - An array of URLs to check.
 * @returns {Promise<string[]>} - A promise that resolves to an array of working URLs.
 */
export async function filterWorkingUrls(urls) {
  const workingUrls = [];

  const checkUrl = async (url) => {
    try {
      const response = await axios.get(url);
      if (response.status === 200) {
        workingUrls.push(url);
      }
    } catch (_) {
      // Handle the error silently (URL might be down or invalid)
    }
  };

  await Promise.all(urls.map(checkUrl));

  return workingUrls;
}
