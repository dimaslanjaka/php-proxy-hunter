<?php

require_once __DIR__ . "/func.php";

$isCli = (php_sapi_name() === 'cli' || defined('STDIN') || (empty($_SERVER['REMOTE_ADDR']) && !isset($_SERVER['HTTP_USER_AGENT']) && count($_SERVER['argv']) > 0));

if (!$isCli) {
  // Allow from any origin
  header("Access-Control-Allow-Origin: *");
  header("Access-Control-Allow-Headers: *");
  header("Access-Control-Allow-Methods: *");
  header('Content-Type: text/plain; charset=utf-8');
}

function randomAndroidUa(string $type = 'chrome')
{
  // Android version array
  $androidVersions = [
    // '5.0' => 'Lollipop',
    // '5.1' => 'Lollipop',
    // '6.0' => 'Marshmallow',
    // '7.0' => 'Nougat',
    // '7.1' => 'Nougat',
    // '8.0' => 'Oreo',
    // '8.1' => 'Oreo',
    // '9.0' => 'Pie',
    '10.0' => 'Android Q',
    '11.0' => 'Red Velvet Cake',
    '12.0' => 'Snow Cone',
    '12.1' => 'Snow Cone v2',
    '13.0' => 'Tiramisu',
    '14.0' => 'Upside Down Cake',
  ];

  // Random Android version
  $androidVersion = array_rand($androidVersions);
  // $androidVersionName = $androidVersions[$androidVersion];

  // echo $androidVersionName . PHP_EOL;

  // Random device manufacturer and model
  $manufacturers = ['Samsung', 'Google', 'Huawei', 'Xiaomi', 'LG'];
  $models = [
    // https://id.wikipedia.org/wiki/Samsung_Galaxy
    'Samsung' => array_unique(['Galaxy S20', 'Galaxy Note 10', 'Galaxy A51', 'Galaxy S10', 'Galaxy S9', 'Galaxy Note 9', 'Galaxy S21', 'Galaxy Note 20', 'Galaxy Z Fold 2', 'Galaxy A71', 'Galaxy S20 FE']),
    // https://en.wikipedia.org/wiki/Google_phone
    'Google' => array_unique(['Pixel 4', 'Pixel 3a', 'Pixel 3', 'Pixel 5', 'Pixel 4a', 'Pixel 4 XL', 'Pixel 3 XL']),
    // https://en.wikipedia.org/wiki/List_of_Huawei_phones
    'Huawei' => array_unique(['P30 Pro', 'Mate 30', 'P40', 'Mate 40 Pro', 'P40 Pro', 'Mate Xs', 'Nova 7i']),
    // https://en.wikipedia.org/wiki/List_of_Xiaomi_products
    'Xiaomi' => array_unique(['Mi 10', 'Redmi Note 9', 'POCO F2 Pro', 'Mi 11', 'Redmi Note 10 Pro', 'POCO X3', 'Mi 10T Pro', 'Redmi Note 4x', 'Redmi Note 5', 'Redmi 6a', 'Mi 8 Lite']),
    // https://en.wikipedia.org/wiki/List_of_LG_mobile_phones
    'LG' => array_unique(['G8 ThinQ', 'V60 ThinQ', 'Stylo 6', 'Velvet', 'Wing', 'K92', 'Q92'])
  ];

  $manufacturer = $manufacturers[array_rand($manufacturers)];
  $model = $models[$manufacturer][array_rand($models[$manufacturer])];

  // echo $manufacturer . PHP_EOL;
  // echo $model . PHP_EOL;

  // Random version numbers for AppleWebKit, Chrome, and Mobile Safari
  $appleWebKitVersion = mt_rand(500, 700) . '.' . mt_rand(0, 99);
  $chromeVersion = mt_rand(70, 99) . '.0.' . mt_rand(1000, 9999);
  $mobileSafariVersion = mt_rand(500, 700) . '.' . mt_rand(0, 99);

  // Generate chrome user-agent string
  $chrome = "Mozilla/5.0 (Linux; Android $androidVersion; $manufacturer $model) AppleWebKit/$appleWebKitVersion (KHTML, like Gecko) Chrome/$chromeVersion Mobile Safari/$mobileSafariVersion";

  // Random Firefox version
  $firefoxVersion = mt_rand(60, 90) . '.0';

  // Generate firefox user-agent string for Mozilla Firefox on Android with randomized version
  $firefoxModel = getRandomItemFromArray(['Mobile', 'Tablet']);
  $firefox = "Mozilla/5.0 (Android $androidVersion; $firefoxModel; rv:$firefoxVersion) Gecko/$firefoxVersion Firefox/$firefoxVersion";

  return $type == 'chrome' ? $chrome : $firefox;
}

function randomIosUa(string $type = 'chrome')
{
  $chrome_version = rand(70, 100); // Generating a random Chrome version between 70 and 100
  $ios_version = rand(9, 15); // Generating a random iOS version between 9 and 15
  $safari_version = rand(600, 700); // Generating a random Safari version between 600 and 700
  $build_version = "15E" . rand(100, 999); // Generating a random build version in the format 15EXXX

  $chrome = "Mozilla/5.0 (iPhone; CPU iPhone OS $ios_version like Mac OS X) AppleWebKit/$safari_version.1 (KHTML, like Gecko) CriOS/$chrome_version.0.0.0 Mobile/$build_version Safari/$safari_version.1";

  $firefox_version = rand(80, 100); // Generating a random Firefox version between 80 and 100

  $firefox = "Mozilla/5.0 (iPhone; CPU iPhone OS $ios_version like Mac OS X) AppleWebKit/$safari_version.1 (KHTML, like Gecko) FxiOS/$firefox_version.0 Mobile/$build_version Safari/$safari_version.1";


  return $type == 'chrome' ? $chrome : $firefox;
}

$max = 1;
if (isset($_REQUEST['max'])) {
  $max = intval($_REQUEST['max']);
}
$type = 'random';
// browser type [firefox, chrome]
if (isset($_REQUEST['type'])) {
  $type = $_REQUEST['type'];
}
$os = 'random';
if (isset($_REQUEST['os'])) {
  $os = $_REQUEST['os'];
}

for ($i = 0; $i < $max; $i++) {
  if ($type == 'random') {
    $uaType = getRandomItemFromArray(['chrome', 'firefox']);
  } else {
    $uaType = $type;
  }
  $android = randomAndroidUa($uaType);
  $ios = randomIosUa($uaType);
  if ($os == 'random') {
    echo getRandomItemFromArray([$ios, $android]) . PHP_EOL;
  } else if ($os == 'android') {
    echo $android . PHP_EOL;
  } else if ($os == 'ios') {
    echo $ios . PHP_EOL;
  }
}

// http://dev.webmanajemen.com/useragent.php?type=[chrome,firefox]&max=[n]&os=[android,ios]