<?php

// proxies writer

require_once __DIR__ . '/func.php';
require_once __DIR__ . '/vendor/autoload.php';

use PhpProxyHunter\geoPlugin;
use PhpProxyHunter\geoPlugin2;
use PhpProxyHunter\ProxyDB;
use function Annexare\Countries\countries;

if (!$isCli) header('Content-Type:text/plain; charset=UTF-8');

if (gethostname() !== 'DESKTOP-JVTSJ6I') {
  if (file_exists(__DIR__ . '/proxyChecker.lock')) {
    exit('another process still running');
  }
}

$geoplugin = new geoPlugin();
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

// write working proxies
file_put_contents(__DIR__ . '/working.txt', $impl);

$fileUntested = __DIR__ . '/proxies.txt';

if (!$isCli) {
  if (!isset($_COOKIE['rewrite_cookies'])) {
    // Set the cookie to expire in 5 minutes (300 seconds)
    $cookie_value = md5(date(DATE_RFC3339));
    setcookie('rewrite_cookies', $cookie_value, time() + 300, '/');

    // rewrite working proxies to be checked again later
    file_put_contents($fileUntested, PHP_EOL . $impl . PHP_EOL, FILE_APPEND);
    // filter only IP:PORT each lines
    rewriteIpPortFile($fileUntested);
    // remove duplicate proxies
    removeDuplicateLines($fileUntested);
  }
}

$untested = countNonEmptyLines($fileUntested);
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
      $select = $db->select($item['proxy']);
      if (!isset($item['useragent'])) {
        if (!empty($select)) {
          if (isset($select[0]['useragent'])) {
            $item['useragent'] = $select[0]['useragent'];
          }
        }

        if (!isset($item['useragent'])) {
          $item['useragent'] = randomWindowsUa();
          echo "EX: set useragent " . $item['proxy'] . PHP_EOL;
          $profiles[$found] = $item;
          $db->updateData($item['proxy'], ['useragent' => $item['useragent']]);
        }
      }
    } else {
      if (!isset($test['useragent'])) {
        if (!empty($select)) {
          if (isset($select[0]['useragent'])) {
            $test['useragent'] = $select[0]['useragent'];
          }
        }
        if (!isset($test['useragent'])) {
          $test['useragent'] = randomWindowsUa();
          echo "TEST: set useragent " . $test['proxy'] . PHP_EOL;
          $db->updateData($test['proxy'], ['useragent' => $test['useragent']]);
        }
      }
      $profiles[] = $test;
    }
  }
}

foreach ($profiles as $item) {
  $found = findByProxy($profiles, $item['proxy']);
  if (!is_null($found)) {
    // determine IP language from country
    if (!isset($item['lang'])) {
      try {
        $countries = array_values(countries());
        $filterCountry = array_filter($countries, function ($country) use ($item) {
          return trim(strtolower($country['name'])) == trim(strtolower($item['country']));
        });
        if (!empty($filterCountry)) {
          $lang = array_values($filterCountry)[0]['languages'][0];
          $item['lang'] = $lang;
          $profiles[$found] = $item;
          $db->updateData($item['proxy'], ['lang' => $item['lang']]);
        }
      } catch (\Throwable $th) {
        //throw $th;
      }
    }

    // determine longitude and latitude
    if (!isset($item['latitude']) || !isset($item['longitude'])) {
      list($ip, $port) = explode(':', $item['proxy']);
      $geo = $geoplugin->locate($ip);
      if (is_string($geo)) {
        $decodedData = json_decode($geo, true);
        if ($decodedData !== null && json_last_error() === JSON_ERROR_NONE) {
          if (isset($decodedData['geoplugin_status']) && isset($decodedData['geoplugin_message']) && $decodedData['geoplugin_status'] == 429 && strpos($decodedData['geoplugin_message'], 'too many request') !== false) {
            // delete cache when response failed
            if (file_exists($geoplugin->cacheFile)) unlink($geoplugin->cacheFile);
            $geo2 = new geoPlugin2();
            $geoplugin = $geo2->locate($ip);
          }
        }
        if ($geoplugin->longitude != null) {
          $item['longitude'] = $geoplugin->longitude;
          $db->updateData($item['proxy'], ['longitude' => $geoplugin->longitude]);
          // apply
          $profiles[$found] = $item;
        }
        if ($geoplugin->latitude != null) {
          $db->updateData($item['proxy'], ['latitude' => $geoplugin->latitude]);
          $item['latitude'] = $geoplugin->latitude;
          // apply
          $profiles[$found] = $item;
        }
      }
    }

    // determine webgl driver
    if (!isset($item['driver'])) {
      $vendor_data = array(
        "Google Inc. (Intel)" => array(
          "renderers" => array("Intel Iris OpenGL Engine", "ANGLE (Intel, Intel(R) HD Graphics 400 Direct3D11 vs_5_0 ps_5_0)", "ANGLE (NVIDIA, NVIDIA GeForce GTX 660 Direct3D11 vs_5_0 ps_5_0, D3D11)"),
          "webgl_vendors" => array("Intel Inc.")
        ),
        "Google Inc. (NVIDIA)" => array(
          "renderers" => array("NVIDIA GeForce Renderer"),
          "webgl_vendors" => array("NVIDIA Corporation")
        ),
        "Microsoft Corporation" => array(
          "renderers" => array("AMD Radeon Pro Renderer"),
          "webgl_vendors" => array("AMD Inc.")
        ),
        "Apple Inc." => array(
          "renderers" => array("Apple Renderer"),
          "webgl_vendors" => array("Apple Inc.")
        ),
        "Mozilla" => array(
          "renderers" => array("ANGLE (Intel, Intel(R) HD Graphics 400 Direct3D11 vs_5_0 ps_5_0), or similar"),
          "webgl_vendors" => array("Google Inc. (Intel)")
        )
      );

      // Get a random key from the vendor_data array
      $random_vendor = array_rand($vendor_data);

      // Get the corresponding value for the random key
      $random_item = array($random_vendor => $vendor_data[$random_vendor]);

      // apply
      $item['driver'] = $random_item;
      $profiles[$found] = $item;
    }

    $select = $db->select($item['proxy']);
    if ($select != false && !empty($select)) {
      $from_db = $select[0];
      if (!isset($item['browser_vendor']) || is_null($item['browser_vendor'])) {
        if (isset($from_db['browser_vendor'])) {
          // update from database
          $item['browser_vendor'] = $from_db['browser_vendor'];
          $item['webgl_renderer'] = $from_db['webgl_renderer'];
          $item['webgl_vendor'] = $from_db['webgl_vendor'];
        } else {
          $webgl_data = random_webgl_data();
          $item['browser_vendor'] = $webgl_data['browser_vendor'];
          $item['webgl_renderer'] = $webgl_data['webgl_renderer'];
          $item['webgl_vendor'] = $webgl_data['webgl_vendor'];
          $db->updateData($item['proxy'], $webgl_data);
        }
      }
      // delete dead proxy
      $status = $from_db['status'];
      if (!is_null($status)) {
        if (trim(strtolower($status)) !== 'active') {
          unset($profiles[$found]);
          echo $item['proxy'] . ' deleted' . PHP_EOL;
        }
      }
    }
  }
}

echo "re-index profiles";

// Reindex the array
$profiles = array_values($profiles);

// Remove duplicate objects based on the 'proxy' key
$uniqueObjects = removeDuplicateObjectsByKey($profiles, 'proxy');

// Encode the uniqueObjects array back to JSON
$uniqueJsonData = json_encode($uniqueObjects, JSON_PRETTY_PRINT);

// write the modified profiles
file_put_contents($fileProfiles, $uniqueJsonData);

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

function removeDuplicateObjectsByKey($array, $key)
{
  $uniqueValues = [];
  $uniqueObjects = [];

  foreach ($array as $object) {
    // Check if the value of the specified key already exists in the uniqueValues array
    if (!in_array($object[$key], $uniqueValues)) {
      // If not, add it to the uniqueValues array and add the object to the uniqueObjects array
      $uniqueValues[] = $object[$key];
      $uniqueObjects[] = $object;
    }
  }

  return $uniqueObjects;
}
