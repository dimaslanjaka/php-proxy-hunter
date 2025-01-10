import _ from 'lodash';

/**
 * Generates a random user agent string for Windows operating system.
 *
 * @returns {string} Random user agent string.
 */
export function randomWindowsUA() {
  // Array of Windows versions
  const windowsVersions = ['Windows 7', 'Windows 8', 'Windows 10', 'Windows 11'];

  // Array of Chrome versions
  const chromeVersions = [
    '86.0.4240',
    '98.0.4758',
    '100.0.4896',
    '105.0.5312',
    '110.0.5461',
    '115.0.5623',
    '120.0.5768',
    '124.0.6367.78', // Windows and Linux version
    '124.0.6367.79', // Mac version
    '124.0.6367.82' // Android version
  ];

  // Randomly select a Windows version
  const randomWindows = _.sample(windowsVersions);

  // Randomly select a Chrome version
  const randomChrome = _.sample(chromeVersions);

  // Generate random Safari version and AppleWebKit version
  const randomSafariVersion = `${_.random(600, 700)}.${_.random(0, 99)}`;
  const randomAppleWebkitVersion = `${_.random(500, 600)}.${_.random(0, 99)}`;

  // Construct and return the user agent string
  return `Mozilla/5.0 (${randomWindows}) AppleWebKit/${randomAppleWebkitVersion} (KHTML, like Gecko) Chrome/${randomChrome} Safari/${randomSafariVersion}`;
}
