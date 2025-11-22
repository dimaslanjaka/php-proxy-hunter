<?php

function getFullUrl($path) {
  // Minimal implementation: normalize with unixPath() and map using PHP_PROXY_HUNTER_PROJECT_ROOT
  // Determine protocol and host (safe for CLI)
  $isHttps  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
  $protocol = $isHttps ? 'https://' : 'http://';
  $host     = !empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : (!empty($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'localhost');

  // If absolute URL passed, return it unchanged
  if (preg_match('#^https?://#i', $path)) {
    return $path;
  }

  // normalize using unixPath helper (composer autoload assumed)
  $normalized = unixPath($path);

  // Map against PHP_PROXY_HUNTER_PROJECT_ROOT (assumed defined by project)
  $relative = $normalized;
  if (defined('PHP_PROXY_HUNTER_PROJECT_ROOT') && !empty(PHP_PROXY_HUNTER_PROJECT_ROOT)) {
    // Normalize project root then remove it from the path (replace with empty string)
    $rootRaw  = realpath(PHP_PROXY_HUNTER_PROJECT_ROOT) ?: PHP_PROXY_HUNTER_PROJECT_ROOT;
    $root     = unixPath(rtrim($rootRaw, '/\\'));
    $relative = preg_replace('#^' . preg_quote($root, '#') . '#i', '', $normalized);
    // collapse duplicate slashes
    $relative = preg_replace('#/{2,}#', '/', $relative);
  }

  // ensure leading slash
  $relative = '/' . ltrim($relative, '/');

  return $protocol . $host . $relative;
}
