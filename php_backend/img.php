<?php

require_once __DIR__ . '/shared.php';

// Simple image proxy with 1-day cache

$CACHE_DIR = __DIR__ . '/../tmp/image-cache';
$CACHE_TTL = 86400;
// 24h
$MAX_BYTES     = 5 * 1024 * 1024;
$ALLOWED_HOSTS = [];
// restrict here if needed
$DEFAULT_TIMEOUT = 10;

PhpProxyHunter\Server::allowCors();

$request = parseQueryOrPostBody();
$raw     = isset($request['url']) ? $request['url'] : '';
// match JS decodeURIComponent
$url = rawurldecode($raw);
// check is base64
if (isValidBase64($url)) {
  $url = base64_decode($url);
}

$isValidUrl = filter_var($url, FILTER_VALIDATE_URL) && preg_match('/^https?:\/\//i', $url);
if (empty($url)) {
  send_error(400, 'Missing url');
} elseif (!$isValidUrl) {
  send_error(400, 'Invalid url');
}

// Validate URL
$parts = parse_url($url);
if (!$parts || !in_array($parts['scheme'], ['http', 'https'])) {
  send_error(400, 'Invalid URL');
}
if ($ALLOWED_HOSTS && !in_array(strtolower($parts['host']), array_map('strtolower', $ALLOWED_HOSTS))) {
  send_error(403, 'Host not allowed');
}

// Cache paths
if ($CACHE_DIR && !is_dir($CACHE_DIR)) {
  @mkdir($CACHE_DIR, 0755, true);
}
$cacheKey  = sha1($url);
$cacheFile = $CACHE_DIR . "/$cacheKey.bin";
$metaFile  = $cacheFile . '.meta';

// Bypass cache if refresh=1
$forceRefresh = isset($_GET['refresh']) && $_GET['refresh'] == '1';

// Serve from cache
if (!$forceRefresh && file_exists($cacheFile) && (time() - filemtime($cacheFile) < $CACHE_TTL)) {
  $meta = @json_decode(@file_get_contents($metaFile), true);
  if ($meta && isset($meta['content_type'])) {
    header("Content-Type: {$meta['content_type']}");
    header('Content-Length: ' . filesize($cacheFile));
    header("Cache-Control: public, max-age={$CACHE_TTL}");
    readfile($cacheFile);
    exit;
  }
}

// Fetch and stream
$fp = fopen($cacheFile . '.tmp', 'wb');
if (!$fp) {
  send_error(500, 'Cache write failed');
}

$headers       = [];
$bytesReceived = 0;

$ch = curl_init($url);
curl_setopt_array($ch, [
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_RETURNTRANSFER => false,
  CURLOPT_TIMEOUT        => $DEFAULT_TIMEOUT,
  CURLOPT_CONNECTTIMEOUT => 5,
  CURLOPT_BUFFERSIZE     => 8192,
  CURLOPT_HEADERFUNCTION => function ($ch, $header) use (&$headers) {
    $len = strlen($header);
    $parts = explode(':', $header, 2);
    if (count($parts) == 2) {
      $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
    }
    return $len;
  },
  CURLOPT_WRITEFUNCTION => function ($ch, $data) use (&$bytesReceived, $MAX_BYTES, $fp) {
    $len = strlen($data);
    $bytesReceived += $len;
    if ($bytesReceived > $MAX_BYTES) {
      return 0;
    }
    fwrite($fp, $data);
    echo $data;
    return $len;
  },
]);

// Send buffered output only after headers parsed
ob_start();

$ok   = curl_exec($ch);
$err  = curl_errno($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if (!$ok || $http >= 400) {
  ob_end_clean();
  fclose($fp);
  @unlink($cacheFile . '.tmp');
  send_error(502, "Fetch failed (HTTP $http, curl $err)");
}

$contentType = isset($headers['content-type']) ? $headers['content-type'] : 'application/octet-stream';
if (stripos($contentType, 'image/') !== 0) {
  ob_end_clean();
  fclose($fp);
  @unlink($cacheFile . '.tmp');
  send_error(415, 'Not an image');
}

// success: commit cache
fclose($fp);
rename($cacheFile . '.tmp', $cacheFile);
file_put_contents($metaFile, json_encode(['content_type' => $contentType, 'time' => time()]));

// Send headers now
header_remove();
header("Content-Type: $contentType");
header("Cache-Control: public, max-age=$CACHE_TTL");

// flush content
ob_end_flush();

function send_error($code, $msg) {
  http_response_code($code);
  header('Content-Type: text/plain; charset=utf-8');
  exit($msg);
}

function isValidBase64($str) {
  $decoded = base64_decode($str, true);
  return ($decoded !== false && base64_encode($decoded) === $str) ? true : false;
}
