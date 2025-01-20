<?php

class CustomPasswordHasher
{

  public static function hash($password)
  {
    $salt = bin2hex(random_bytes(16));  // Generate a 16-byte random salt
    return hash('sha256', $password . $salt) . '$' . $salt;  // Hash the password and return it with the salt
  }

  public static function verify($password, $encoded)
  {
    list($passwordHash, $salt) = explode('$', $encoded, 2);
    return $passwordHash === hash('sha256', $password . $salt);  // Compare hashes
  }

  public static function summary($encoded)
  {
    list($passwordHash, $salt) = explode('$', $encoded, 2);
    return ['algorithm' => 'custom', 'salt' => $salt];
  }
}
