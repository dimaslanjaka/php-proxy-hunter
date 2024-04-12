<?php

require_once __DIR__ . "/func.php";

$isCli = (php_sapi_name() === 'cli' || defined('STDIN') || (empty($_SERVER['REMOTE_ADDR']) && !isset($_SERVER['HTTP_USER_AGENT']) && count($_SERVER['argv']) > 0));

if (!$isCli) {
  // Allow from any origin
  header("Access-Control-Allow-Origin: *");
  header("Access-Control-Allow-Headers: *");
  header("Access-Control-Allow-Methods: *");
}

// set output buffering to zero
// avoid error while running on CLI
if (!$isCli) {
  ini_set('output_buffering', 0);
  if (ob_get_level() == 0) {
    ob_start();
  }
  if (function_exists('header')) {
    header('Content-Type: text/plain; charset=utf-8');
    header('X-Powered-By: L3n4r0x');
  }
}

$filePath = __DIR__ . "/socks.txt";
$workingPath = __DIR__ . "/socks-working.txt";
$deadPath = __DIR__ . "/socks-dead.txt";
$workingProxies = [];

rewriteIpPortFile($filePath);
rewriteIpPortFile($deadPath);
setFilePermissions([$filePath, $deadPath]);
shuffleChecks();

/**
 * run proxies check shuffled
 */
function shuffleChecks()
{
  global $filePath, $workingPath, $workingProxies, $deadPath, $isCli;

  // Read lines of the file into an array
  $lines = file($filePath, FILE_IGNORE_NEW_LINES);
  if (empty(array_filter($lines))) {
    echo "proxies empty, respawning dead proxies\n\n";
    // respawn dead proxies
    rename($deadPath, $filePath);
    // repeat
    return shuffleChecks();
  }

  // Shuffle the array
  shuffle($lines);

  // Iterate through the shuffled lines
  foreach ($lines as $line) {
    // if (checkProxyLine($line) == "break") break;
    $check = checkProxy(trim($line));
    echo trim($line) . " " . ($check['result'] ? "working" : "dead") . " latency " . $check['latency'] . " ms" . PHP_EOL;
    if (!$check['result']) {
      removeStringAndMoveToFile($filePath, $deadPath, $line);
    } else {
      $proxy = trim($line);
      $latency = $check['latency'];
      $item = "$proxy|$latency|CURLPROXY_SOCKS5";
      if (!in_array($item, $workingProxies)) {
        // If the item doesn't exist, push it into the array
        $workingProxies[] = $item;
      }
      // write working proxy
      file_put_contents($workingPath, join("\n", $workingProxies));
    }
    if (!$isCli && ob_get_level() > 0) {
      // LIVE output buffering on web server
      flush();
      ob_flush();
    }
  }

  // rewrite all working proxies
  if (count($workingProxies) > 1) file_put_contents($workingPath, join("\n", $workingProxies));
}

function checkProxy($proxy)
{
  $start = microtime(true);

  // Splitting the proxy address into IP and port
  // list($ip, $port) = explode(':', $proxy);

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, "https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-1254560890123456"); // Change URL to the one you want to test
  curl_setopt($ch, CURLOPT_PROXY, $proxy);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Timeout in seconds
  curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5); // Change to CURLPROXY_SOCKS4 if needed

  // Execute the request
  $output = curl_exec($ch);
  $totalTime = microtime(true) - $start;
  $latency = 0;
  // $output === false is error
  $result = $output !== false;
  if ($result) $latency = round($totalTime * 1000, 2);

  // Close cURL resource
  curl_close($ch);

  return ["result" => $result, "latency" => $latency];
}
