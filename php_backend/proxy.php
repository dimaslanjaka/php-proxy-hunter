<?php

require_once __DIR__ . '/shared.php';

// General-purpose proxy with CORS bypass for assets (JS, CSS, TXT, JSON, etc.)
// 1-day cache

$CACHE_DIR = __DIR__ . '/../tmp/asset-cache';
$CACHE_TTL = 86400;
// 24h
$MAX_BYTES = 10 * 1024 * 1024;
// 10MB limit
$ALLOWED_HOSTS = [];
// restrict here if needed (leave empty for all)
$DEFAULT_TIMEOUT = 10;

// Allowed MIME types for this proxy
$ALLOWED_TYPES = [
  'application/javascript',
  'text/javascript',
  'text/css',
  'text/plain',
  'application/json',
  'image/svg+xml',
  'text/html',
  'application/xml',
  'text/xml',
];

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
  send_error(400, 'Missing url parameter');
} elseif (!$isValidUrl) {
  send_error(400, 'Invalid url format');
}

// Validate URL
$parts = parse_url($url);
if (!$parts || !in_array($parts['scheme'], ['http', 'https'])) {
  send_error(400, 'Invalid URL scheme (must be http or https)');
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
    header("Content-Type: {$meta['content_type']}; charset=utf-8");
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

// Build the base cURL handle using shared helper
$ch = buildCurl(null, 'http', $url, [], null, null, 'GET', null, 0);
// forward user-agent
curl_setopt($ch, CURLOPT_USERAGENT, isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
// override to stream output and capture headers incrementally
curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
curl_setopt($ch, CURLOPT_BUFFERSIZE, 8192);
curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, $header) use (&$headers) {
  $len   = strlen($header);
  $parts = explode(':', $header, 2);
  if (count($parts) == 2) {
    $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
  }
  return $len;
});
curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) use (&$bytesReceived, $MAX_BYTES, $fp) {
  $len = strlen($data);
  $bytesReceived += $len;
  if ($bytesReceived > $MAX_BYTES) {
    return 0;
    // abort
  }
  fwrite($fp, $data);
  echo $data;
  return $len;
});

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
  send_error(502, "Fetch failed (HTTP $http, curl $err, received $bytesReceived bytes)");
}

$contentType = isset($headers['content-type']) ? explode(';', $headers['content-type'])[0] : 'application/octet-stream';
$contentType = trim($contentType);

// Validate content type
if (!in_array($contentType, $ALLOWED_TYPES)) {
  ob_end_clean();
  fclose($fp);
  @unlink($cacheFile . '.tmp');
  send_error(415, "Content-Type '$contentType' not allowed. Allowed: " . implode(', ', $ALLOWED_TYPES));
}

// success: commit cache
fclose($fp);
rename($cacheFile . '.tmp', $cacheFile);
file_put_contents($metaFile, json_encode(['content_type' => $contentType, 'time' => time()]));

// Send headers now (without Content-Length for now to allow streaming)
header_remove();
header("Content-Type: $contentType; charset=utf-8");
header("Cache-Control: public, max-age=$CACHE_TTL");
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// flush content
ob_end_flush();

function send_error(int $code, string $msg): void
{
  http_response_code($code);
  header('Content-Type: text/plain; charset=utf-8');
  exit($msg);
}

function isValidBase64(string $str): bool
{
  $decoded = base64_decode($str, true);
  return ($decoded !== false && base64_encode($decoded) === $str) ? true : false;
}
