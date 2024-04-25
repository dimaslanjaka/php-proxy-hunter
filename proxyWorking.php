<?php

// proxies writer

require __DIR__ . '/func.php';

use PhpProxyHunter\ProxyDB;
use function Annexare\Countries\countries;

if (!$isCli) header('Content-Type:text/plain; charset=UTF-8');

if (gethostname() !== 'DESKTOP-JVTSJ6I') {
  if (file_exists(__DIR__ . '/proxyChecker.lock')) {
    exit('another process still running');
  }
}

$db = new ProxyDB();
$working = $db->getWorkingProxies();
$private = $db->getPrivateProxies();

$impl = implode(PHP_EOL, array_map(function ($item) {
  return implode("|", [$item['proxy'], $item['latency'] ? $item['latency'] : '-', strtoupper($item['type']), $item['region'] ? $item['region'] : '-', $item['city'] ? $item['city'] : '-', $item['country'] ? $item['country'] : '-', $item['timezone'] ? $item['timezone'] : '-', $item['last_check'] ? $item['last_check'] : '-']);
}, $working));

// $header = 'PROXY|LATENCY|TYPE|REGION|CITY|COUNTRY|TIMEZONE|LAST CHECK DATE';
// echo implode(PHP_EOL, [$header, $impl]);

// Explode the input into an array of lines
$lines = explode("\n", $impl);

// Sort the lines alphabetically
sort($lines);

$impl = join("\n", $lines);

file_put_contents(__DIR__ . '/working.txt', $impl);

if (!$isCli) {
  if (!isset($_COOKIE['rewrite_cookies'])) {
    // Set the cookie to expire in 5 minutes (300 seconds)
    $cookie_value = md5(date(DATE_RFC3339));
    setcookie('rewrite_cookies', $cookie_value, time() + 300, '/');

    // rewrite working proxies to be checked again later
    file_put_contents(__DIR__ . '/proxies.txt', PHP_EOL . $impl . PHP_EOL, FILE_APPEND);
  }
}

$untested = countNonEmptyLines(__DIR__ . '/proxies.txt');
$dead = countNonEmptyLines(__DIR__ . '/dead.txt');
echo "total working proxies " . count($working) . PHP_EOL;
echo "total private proxies " . count($private) . PHP_EOL;
echo "total dead proxies $dead" . PHP_EOL;
echo "total untested proxies $untested" . PHP_EOL;

file_put_contents(__DIR__ . '/status.json', json_encode(['working' => count($working), 'dead' => $dead, 'untested' => $untested, 'private' => count($private)]));

// write for python profiles

$fileProfiles = __DIR__ . '/profiles-proxy.json';
$profiles = [];
if (file_exists($fileProfiles)) {
  $profiles = json_decode(file_get_contents($fileProfiles), true);
}

if (empty($profiles)) {
  // write init
  $originalProfiles = array_map(function ($item) {
    if (!isset($item['useragent'])) $item['useragent'] = randomWindowsUa();
    return $item;
  }, $working);
  file_put_contents($fileProfiles, json_encode($originalProfiles));
  $profiles = $originalProfiles;
  $count = count($originalProfiles);
  echo "write init profile ($count)";
}

// modify useragent each IP
if (!empty($profiles)) {
  foreach ($working as $test) {
    $found = findByProxy($profiles, $test['proxy']);
    if (!is_null($found)) {
      $item = $profiles[$found];
      if (!isset($item['useragent'])) {
        $item['useragent'] = randomWindowsUa();
        echo "EX: set useragent " . $item['proxy'] . PHP_EOL;
        $profiles[$found] = $item;
        // $db->updateData($item['proxy'], ['useragent' => $item['useragent']]);
      }
    } else {
      if (!isset($test['useragent'])) {
        $test['useragent'] = randomWindowsUa();
        echo "TEST: set useragent " . $test['proxy'] . PHP_EOL;
        // $db->updateData($test['proxy'], ['useragent' => $test['useragent']]);
      }
      $profiles[] = $test;
    }
  }
}

$countries = array_values(countries());
foreach ($profiles as $item) {
  // determine IP language from country
  $found = findByProxy($profiles, $item['proxy']);
  if (!is_null($found)) {
    if (!isset($item['lang'])) {
      $filterCountry = array_filter($countries, function ($country) use ($item) {
        return trim(strtolower($country['name'])) == trim(strtolower($item['country']));
      });
      if (!empty($filterCountry)) {
        $lang = array_values($filterCountry)[0]['languages'][0];
        $item['lang'] = $lang;
        $profiles[$found] = $item;
        $db->updateData($item['proxy'], ['lang' => $item['lang']]);
      }
    }
    // delete dead proxy
    $select = $db->select($item['proxy']);
    if ($select != false && !empty($select)) {
      $status = $select[0]['status'];
      if (trim(strtolower($status)) != 'active') {
        unset($profiles[$found]);
        echo ($item['proxy'] . ' deleted' . PHP_EOL);
      }
    }
  }
}

file_put_contents($fileProfiles, json_encode($profiles, JSON_PRETTY_PRINT));

/**
 * Find the index of an item in an array based on its 'proxy' value.
 *
 * @param array $array The array to search through.
 * @param string $proxy The value of the 'proxy' key to search for.
 * @return int|null The index of the found item, or null if not found.
 */
function findByProxy($array, $proxy)
{
  foreach ($array as $key => $item) {
    if ($item['proxy'] === $proxy) {
      return $key; // Return the index of the found item
    }
  }
  return null; // Proxy not found
}
