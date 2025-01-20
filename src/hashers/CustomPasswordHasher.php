<?php

use Dotenv\Dotenv;

class CustomPasswordHasher
{
  private static function getSecretKey()
  {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
    $dotenv->load();
    return $_ENV['DJANGO_SECRET_KEY'] ?? 'default_secret_key';
  }

  public static function getSalt()
  {
    $secretKey = self::getSecretKey();
    // Generate deterministic salt and convert it to hex
    return substr(hash('sha256', $secretKey), 0, 16);
  }

  public static function hash($password)
  {
    $salt = self::getSalt();
    $hashedPassword = hash('sha256', $password . $salt); // Combine password and salt
    return $hashedPassword . '$' . $salt; // Return hashed password with salt
  }

  public static function verify($password, $encoded)
  {
    list($passwordHash, $salt) = explode('$', $encoded, 2);
    $hashedPassword = hash('sha256', $password . $salt);
    return $passwordHash === $hashedPassword; // Compare hashes
  }

  public static function summary($encoded)
  {
    list($passwordHash, $salt) = explode('$', $encoded, 2);
    return ['algorithm' => 'custom', 'salt' => $salt];
  }
}
