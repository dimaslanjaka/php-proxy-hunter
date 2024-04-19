<?php

require_once __DIR__ . '/func.php';

use PhpProxyHunter\ProxyDB;
use PhpProxyHunter\geoPlugin;

if (function_exists('header')) header('Content-Type: application/json; charset=UTF-8');

$lockFilePath = __DIR__ . "/proxyChecker.lock";

if (file_exists($lockFilePath)) {
  exit(json_encode(['error' => 'another process still running']));
} else {
  file_put_contents($lockFilePath, '');
}
function exitProcess()
{
  global $lockFilePath, $statusFile;
  if (file_exists($lockFilePath)) unlink($lockFilePath);
}
register_shutdown_function('exitProcess');

$geoplugin = new geoPlugin();
$db = new ProxyDB();

$proxy = '112.30.155.83:12792';
if (isset($_REQUEST['proxy'])) {
  $string = trim($_REQUEST['proxy']);
  // Regular expression to match IP:PORT pattern
  $pattern = '/(\d+\.\d+\.\d+\.\d+):(\d+)/';

  // Match the pattern in the string
  preg_match($pattern, $string, $matches);

  if (count($matches) === 3) {
    $ip = $matches[1];
    $port = $matches[2];
    // echo "IP: $ip, Port: $port \n";
    if (!empty(trim($ip)) && !empty(trim($port))) {
      $proxy = preg_replace('/\s+/', '', "$ip:$port");
    }
  }
}

list($ip, $port) = explode(':', $proxy);

$geoplugin->locate($ip);
$json = $geoplugin->jsonSerialize();
unset($json['host']);
echo json_encode($json);
$db->update($proxy, null, $geoplugin->region, $geoplugin->city, $geoplugin->countryName, null, null, $geoplugin->timezone);

// echo "Geolocation results for {$geoplugin->ip}\n" .
//   "City: {$geoplugin->city} \n" .
//   "Region: {$geoplugin->region} \n" .
//   "Region Code: {$geoplugin->regionCode} \n" .
//   "Region Name: {$geoplugin->regionName} \n" .
//   "DMA Code: {$geoplugin->dmaCode} \n" .
//   "Country Name: {$geoplugin->countryName} \n" .
//   "Country Code: {$geoplugin->countryCode} \n" .
//   "In the EU?: {$geoplugin->inEU} \n" .
//   "EU VAT Rate: {$geoplugin->euVATrate} \n" .
//   "Latitude: {$geoplugin->latitude} \n" .
//   "Longitude: {$geoplugin->longitude} \n" .
//   "Radius of Accuracy (Miles): {$geoplugin->locationAccuracyRadius} \n" .
//   "Timezone: {$geoplugin->timezone}  \n" .
//   "Currency Code: {$geoplugin->currencyCode} \n" .
//   "Currency Symbol: {$geoplugin->currencySymbol} \n" .
//   "Exchange Rate: {$geoplugin->currencyConverter} \n";

// if ($geoplugin->currency != $geoplugin->currencyCode) {
//   //our visitor is not using the same currency as the base currency
//   echo "At todays rate, US$100 will cost you " . $geoplugin->convert(100) . " \n";
// }

// /* find places nearby */
// $nearby = $geoplugin->nearby();
// if (isset($nearby[0]['geoplugin_place'])) {
//   echo "Some places you may wish to visit near " . $geoplugin->city . ": \n";
//   foreach ($nearby as $key => $array) {

//     echo ($key + 1) . ":";
//     echo "\t Place: " . $array['geoplugin_place'] . "";
//     echo "\t Country Code: " . $array['geoplugin_countryCode'] . "";
//     echo "\t Region: " . $array['geoplugin_region'] . "";
//     echo "\t County: " . $array['geoplugin_county'] . "";
//     echo "\t Latitude: " . $array['geoplugin_latitude'] . "";
//     echo "\t Longitude: " . $array['geoplugin_longitude'] . "";
//     echo "\t Distance (miles): " . $array['geoplugin_distanceMiles'] . "";
//     echo "\t Distance (km): " . $array['geoplugin_distanceKilometers'] . "";
//   }
//   echo "\n";
// }
