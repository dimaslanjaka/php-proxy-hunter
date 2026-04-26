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
 * @param string ...$subpaths Optional path segments to append to the project root
 * @return string Absolute path to project root or subpath
 */
function get_project_root(string ...$subpaths): string {
  // Determine the project root using the same strategy as before,
  // but assign to $root so we can optionally append subpaths.
  $root = null;

  // 1) Constant overrides (explicit)
  if (defined('PHP_PROXY_HUNTER_PROJECT_ROOT')) {
    $root = (string) constant('PHP_PROXY_HUNTER_PROJECT_ROOT');
  } elseif (defined('PROJECT_ROOT')) {
    $root = (string) constant('PROJECT_ROOT');
  } else {
    // 2) Environment variable overrides
    $env = getenv('PHP_PROXY_HUNTER_PROJECT_ROOT') ?: getenv('PROJECT_ROOT');
    if (is_string($env) && $env !== '') {
      $root = $env;
    } else {
      // 3) If installed via Composer it will live under .../vendor/<vendor>/<package>/...
      //    returning the segment before '/vendor/' gives the consumer project root.
      $dir           = __DIR__;
      $vendorSegment = DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR;
      $pos           = strpos($dir, $vendorSegment);
      if ($pos !== false) {
        $root = substr($dir, 0, $pos);
      } else {
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
            $root = $candidate;
            break;
          }

          $current = $candidate;
        }

        // 5) Fallback (legacy): two levels up from this file
        if ($root === null) {
          $root = dirname(__DIR__, 2);
        }
      }
    }
  }

  $root = rtrim((string) $root, DIRECTORY_SEPARATOR);

  if (count($subpaths) === 0) {
    return $root;
  }

  $filtered = array_values(array_filter($subpaths, static function ($p) {
    return $p !== null && $p !== '';
  }));

  if (count($filtered) === 0) {
    return $root;
  }

  $path = $root . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $filtered);
  return rtrim($path, DIRECTORY_SEPARATOR);
}
