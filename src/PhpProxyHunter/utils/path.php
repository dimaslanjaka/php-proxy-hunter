<?php

/**
 * Return the project root directory for the consumer application.
 *
 * Strategy (in order):
 * 1. If a consuming app defines the constant `PROJECT_ROOT` (or
 *    `PHP_PROXY_HUNTER_PROJECT_ROOT`) return it. This is the most
 *    explicit and preferred option for production deployments.
 * 2. If an environment variable `PROJECT_ROOT` or
 *    `PHP_PROXY_HUNTER_PROJECT_ROOT` is set, return it.
 * 3. If this package is installed under a `vendor/` directory (Composer),
 *    return the path segment before `/vendor/` — that is the consumer root.
 * 4. Otherwise, walk up parent directories looking for a `composer.json`
 *    and return the containing directory if found.
 * 5. Fallback to two levels up from this file (legacy behaviour).
 *
 * This function is safe to call from both development (repository) and
 * production (installed via Composer) contexts.
 *
 * @return string Absolute path to project root
 */
function get_project_root(): string {
  // 1) Constant overrides (explicit)
  if (defined('PHP_PROXY_HUNTER_PROJECT_ROOT')) {
    return rtrim((string) constant('PHP_PROXY_HUNTER_PROJECT_ROOT'), DIRECTORY_SEPARATOR);
  }

  if (defined('PROJECT_ROOT')) {
    return rtrim((string) constant('PROJECT_ROOT'), DIRECTORY_SEPARATOR);
  }

  // 2) Environment variable overrides
  $env = getenv('PHP_PROXY_HUNTER_PROJECT_ROOT') ?: getenv('PROJECT_ROOT');
  if (is_string($env) && $env !== '') {
    return rtrim($env, DIRECTORY_SEPARATOR);
  }

  // 3) If installed via Composer it will live under .../vendor/<vendor>/<package>/...
  //    returning the segment before '/vendor/' gives the consumer project root.
  $dir           = __DIR__;
  $vendorSegment = DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR;
  $pos           = strpos($dir, $vendorSegment);
  if ($pos !== false) {
    return rtrim(substr($dir, 0, $pos), DIRECTORY_SEPARATOR);
  }

  // 4) Walk up and look for composer.json (limit depth to avoid pathological loops)
  $maxDepth = 10;
  $current  = $dir;
  for ($i = 0; $i < $maxDepth; $i++) {
    $candidate = dirname($current);
    if ($candidate === $current) {
      break;
      // reached filesystem root
    }

    if (file_exists($candidate . DIRECTORY_SEPARATOR . 'composer.json')) {
      return rtrim($candidate, DIRECTORY_SEPARATOR);
    }

    $current = $candidate;
  }

  // 5) Fallback (legacy): two levels up from this file
  return dirname(__DIR__, 2);
}
