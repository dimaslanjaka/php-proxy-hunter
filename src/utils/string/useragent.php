<?php

/**
 * Generates a random user agent string for Windows operating system.
 *
 * @return string Random user agent string.
 */
function randomWindowsUa(): string
{
  // Array of Windows versions
  $windowsVersions = ['Windows 7', 'Windows 8', 'Windows 10', 'Windows 11'];

  // Array of Chrome versions
  $chromeVersions = [
    '86.0.4240',
    '98.0.4758',
    '100.0.4896',
    '105.0.5312',
    '110.0.5461',
    '115.0.5623',
    '120.0.5768',
    '124.0.6367.78', // Windows and Linux version
    '124.0.6367.79', // Mac version
    '124.0.6367.82', // Android version
  ];

  // Randomly select a Windows version
  $randomWindows = $windowsVersions[array_rand($windowsVersions)];

  // Randomly select a Chrome version
  $randomChrome = $chromeVersions[array_rand($chromeVersions)];

  // Generate random Safari version and AppleWebKit version
  $randomSafariVersion      = mt_rand(600, 700) . '.' . mt_rand(0, 99);
  $randomAppleWebKitVersion = mt_rand(500, 600) . '.' . mt_rand(0, 99);

  // Construct and return the user agent string
  return "Mozilla/5.0 ($randomWindows) AppleWebKit/$randomAppleWebKitVersion (KHTML, like Gecko) Chrome/$randomChrome Safari/$randomSafariVersion";
}

/**
 * Generates a random Android user-agent string.
 *
 * @param string $type The type of browser user-agent to generate. Default is 'chrome'.
 * @return string The generated user-agent string.
 */
function randomAndroidUa(string $type = 'chrome'): string
{
  // Android version array
  $androidVersions = [
    '10.0' => 'Android Q',
    '11.0' => 'Red Velvet Cake',
    '12.0' => 'Snow Cone',
    '12.1' => 'Snow Cone v2',
    '13.0' => 'Tiramisu',
    '14.0' => 'Upside Down Cake',
  ];

  // Random Android version
  $androidVersion = array_rand($androidVersions);

  // Random device manufacturer and model
  $manufacturers = ['Samsung', 'Google', 'Huawei', 'Xiaomi', 'LG'];
  $models        = [
    'Samsung' => [
      'Galaxy S20',
      'Galaxy Note 10',
      'Galaxy A51',
      'Galaxy S10',
      'Galaxy S9',
      'Galaxy Note 9',
      'Galaxy S21',
      'Galaxy Note 20',
      'Galaxy Z Fold 2',
      'Galaxy A71',
      'Galaxy S20 FE',
    ],
    'Google' => ['Pixel 4', 'Pixel 3a', 'Pixel 3', 'Pixel 5', 'Pixel 4a', 'Pixel 4 XL', 'Pixel 3 XL'],
    'Huawei' => ['P30 Pro', 'Mate 30', 'P40', 'Mate 40 Pro', 'P40 Pro', 'Mate Xs', 'Nova 7i'],
    'Xiaomi' => [
      'Mi 10',
      'Redmi Note 9',
      'POCO F2 Pro',
      'Mi 11',
      'Redmi Note 10 Pro',
      'POCO X3',
      'Mi 10T Pro',
      'Redmi Note 4x',
      'Redmi Note 5',
      'Redmi 6a',
      'Mi 8 Lite',
    ],
    'LG' => ['G8 ThinQ', 'V60 ThinQ', 'Stylo 6', 'Velvet', 'Wing', 'K92', 'Q92'],
  ];

  $manufacturer = $manufacturers[array_rand($manufacturers)];
  $model        = $models[$manufacturer][array_rand($models[$manufacturer])];

  // Random version numbers for AppleWebKit, Chrome, and Mobile Safari
  $appleWebKitVersion  = mt_rand(500, 700) . '.' . mt_rand(0, 99);
  $chromeVersion       = mt_rand(70, 99) . '.0.' . mt_rand(1000, 9999);
  $mobileSafariVersion = mt_rand(500, 700) . '.' . mt_rand(0, 99);

  // Generate chrome user-agent string
  $chrome = "Mozilla/5.0 (Linux; Android $androidVersion; $manufacturer $model) AppleWebKit/$appleWebKitVersion (KHTML, like Gecko) Chrome/$chromeVersion Mobile Safari/$mobileSafariVersion";

  // Random Firefox version
  $firefoxVersion = mt_rand(60, 90) . '.0';

  // Generate firefox user-agent string for Mozilla Firefox on Android with randomized version
  $firefoxModel = getRandomItemFromArray(['Mobile', 'Tablet']);
  $firefox      = "Mozilla/5.0 (Android $androidVersion; $firefoxModel; rv:$firefoxVersion) Gecko/$firefoxVersion Firefox/$firefoxVersion";

  return $type == 'chrome' ? $chrome : $firefox;
}

/**
 * Generates a random iOS user-agent string.
 *
 * @param string $type The type of browser user-agent to generate. Default is 'chrome'.
 * @return string The generated user-agent string.
 */
function randomIosUa(string $type = 'chrome'): string
{
  $chrome_version = rand(70, 100);
  $ios_version    = rand(9, 15);
  $safari_version = rand(600, 700);
  $build_version  = '15E' . rand(100, 999);

  $chrome = "Mozilla/5.0 (iPhone; CPU iPhone OS $ios_version like Mac OS X) AppleWebKit/$safari_version.1 (KHTML, like Gecko) CriOS/$chrome_version.0.0.0 Mobile/$build_version Safari/$safari_version.1";

  $firefox_version = rand(80, 100);

  $firefox = "Mozilla/5.0 (iPhone; CPU iPhone OS $ios_version like Mac OS X) AppleWebKit/$safari_version.1 (KHTML, like Gecko) FxiOS/$firefox_version.0 Mobile/$build_version Safari/$safari_version.1";

  return $type == 'chrome' ? $chrome : $firefox;
}
