<?php

declare(strict_types=1);

/**
 * Recursively iterate through a multidimensional array and hide values
 * based on a user-defined callback.
 *
 * The callback receives the current element's key and value. When possible
 * (the function detects the callback accepts a third parameter) the immediate
 * parent array key for that element is passed as the third argument. This
 * allows higher-level context to be used when deciding whether to hide a
 * particular value.
 *
 * Callback signature (for documentation only):
 *   function (string|int $key, mixed $value, string|int|null $parentKey = null): bool
 *
 * Parameters:
 * @param array $data The array to process.
 * @param callable $shouldHide A callback which returns true to hide the value.
 *        The callback may accept 2 args ($key, $value) or 3 args
 *        ($key, $value, $parentKey). If the callback declares a third
 *        parameter it will receive the immediate parent key (or null at
 *        top-level). Reflection is used to preserve backward compatibility
 *        with 2-argument callbacks.
 * @param callable|null $maskCallback Optional function to transform hidden values.
 * @param string|int|null $parentKey Internal parameter used during recursion to
 *        forward the parent key. Callers should not set this value.
 * @return array The modified array with hidden data.
 */
function hideArrayData($data, $shouldHide, $maskCallback = null, $parentKey = null) {
  // Provide a default mask callback if none supplied.
  if ($maskCallback === null) {
    $maskCallback = function ($value) {
      return is_string($value) ? str_repeat('*', strlen($value)) : '[HIDDEN]';
    };
  }

  $result = [];

  // Detect whether the provided callback accepts a third argument (parent key).
  $acceptsParent = false;
  try {
    if (is_array($shouldHide)) {
      $ref = new ReflectionMethod($shouldHide[0], $shouldHide[1]);
    } elseif (is_string($shouldHide) && strpos($shouldHide, '::') !== false) {
      $parts  = explode('::', $shouldHide, 2);
      $class  = $parts[0];
      $method = $parts[1];
      $ref    = new ReflectionMethod($class, $method);
    } else {
      $ref = new ReflectionFunction($shouldHide);
    }
    $acceptsParent = $ref->getNumberOfParameters() >= 3;
  } catch (ReflectionException $e) {
    // If reflection fails for any reason, fall back to calling with 2 args.
    $acceptsParent = false;
  }

  foreach ($data as $key => $value) {
    if (is_array($value)) {
      // Determine the parent key to forward for child elements.
      // If the current key is an integer (list index), forward the existing
      // $parentKey so that object properties inside a numeric list receive
      // the container's key (e.g. 'packages'), not the numeric index.
      $nextParent   = is_int($key) ? $parentKey : $key;
      $result[$key] = hideArrayData($value, $shouldHide, $maskCallback, $nextParent);
    } else {
      $should = $acceptsParent ? $shouldHide($key, $value, $parentKey) : $shouldHide($key, $value);
      if ($should) {
        $result[$key] = $maskCallback($value);
      } else {
        $result[$key] = $value;
      }
    }
  }

  return $result;
}

// Check if called directly by cli
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
  // Example usage:
  $data = [
    'username' => 'john_doe',
    'password' => 'secret123',
    'profile'  => [
      'email'     => 'john@example.com',
      'api_token' => 'abcd1234',
      'nested'    => [
        'credit_card' => '4111111111111111',
        'age'         => 30,
      ],
    ],
  ];

  // Example: hide anything whose key name contains "pass", "token", or "card"
  $result = hideArrayData(
    $data,
    // This callback accepts the optional third parameter $parentKey.
    function ($key, $value, $parentKey = null) {
      // Hide sensitive fields if the key itself matches, or if the parent key
      // indicates a sensitive container (for example, 'profile' might contain tokens).
      if (preg_match('/pass|token|card/i', (string) $key)) {
        return true;
      }
      if ($parentKey !== null && preg_match('/profile|credentials/i', (string) $parentKey)) {
        return preg_match('/email|api|card|credit/i', (string) $key);
      }
      return false;
    }
  );

  var_dump($result);
}
