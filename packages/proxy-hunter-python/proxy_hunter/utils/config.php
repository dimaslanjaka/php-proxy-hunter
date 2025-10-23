<?php

/**
 * ConfigDB - PHP port of proxy_hunter.utils.config.ConfigDB
 * Supports SQLite (via PDO) and MySQL (via PDO)
 *
 * Usage:
 * $db = new ConfigDB('sqlite', ['db_path' => 'config.db']);
 * $db->set('key', ['a' => 1]);
 * $val = $db->get('key');
 */

namespace ProxyHunter\Utils;

use PDO;

class ConfigDB
{
  private $driver;
  private $conn;

  public function __construct($driver = 'sqlite', array $opts = [])
  {
    $this->driver = strtolower($driver);

    if ($this->driver === 'sqlite') {
      $dbPath     = $opts['db_path'] ?? 'config.db';
      $dsn        = 'sqlite:' . $dbPath;
      $this->conn = new PDO($dsn);
      // enable exceptions
      $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } elseif ($this->driver === 'mysql') {
      $host       = $opts['host']     ?? '127.0.0.1';
      $user       = $opts['user']     ?? 'root';
      $password   = $opts['password'] ?? '';
      $database   = $opts['database'] ?? 'test';
      $dsn        = "mysql:host={$host};dbname={$database};charset=utf8mb4";
      $this->conn = new PDO($dsn, $user, $password, [
          PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
          PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      ]);
    } else {
      throw new \InvalidArgumentException("Unsupported driver. Use 'sqlite' or 'mysql'.");
    }

    $this->createTable();
  }

  public function __destruct()
  {
    $this->close();
  }

  private function createTable()
  {
    if ($this->driver === 'sqlite') {
      $sql = "CREATE TABLE IF NOT EXISTS config (\n                name TEXT PRIMARY KEY,\n                value TEXT NOT NULL\n            )";
    } else {
      $sql = "CREATE TABLE IF NOT EXISTS config (\n                name VARCHAR(255) PRIMARY KEY,\n                value TEXT NOT NULL\n            )";
    }

    $this->conn->exec($sql);
  }

  private function encode($value)
  {
    // attempt JSON encoding; fall back to string cast
    $encoded = json_encode($value);
    if ($encoded === false || json_last_error() !== JSON_ERROR_NONE) {
      if (is_object($value)) {
        $vars    = get_object_vars($value);
        $encoded = json_encode($vars);
        if ($encoded !== false && json_last_error() === JSON_ERROR_NONE) {
          return $encoded;
        }
      }
      return (string)$value;
    }

    return $encoded;
  }

  private function decode($value)
  {
    $decoded = json_decode($value, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
      return $value;
    }
    return $decoded;
  }

  public function set($key, $value)
  {
    $encoded = $this->encode($value);

    if ($this->driver === 'sqlite') {
      $sql = 'INSERT OR REPLACE INTO config (name, value) VALUES (:name, :value)';
    } else {
      // MySQL: use INSERT ... ON DUPLICATE KEY UPDATE
      $sql = 'INSERT INTO config (name, value) VALUES (:name, :value) ON DUPLICATE KEY UPDATE value = VALUES(value)';
    }

    $stmt = $this->conn->prepare($sql);
    $stmt->execute([':name' => $key, ':value' => $encoded]);
  }

  /**
   * Get a value. If $modelClass is provided and the decoded value is an array,
   * it will attempt to instantiate the class with the array as constructor args
   * (assumes the class accepts an array in its constructor or public properties).
   *
   * @param string $key
   * @param string|null $modelClass
   * @return mixed|null
   */
  public function get($key, $modelClass = null)
  {
    if ($this->driver === 'sqlite') {
      $sql = 'SELECT value FROM config WHERE name = :name';
    } else {
      $sql = 'SELECT value FROM config WHERE name = :name';
    }

    $stmt = $this->conn->prepare($sql);
    $stmt->execute([':name' => $key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
      return null;
    }

    $raw     = $row['value'];
    $decoded = $this->decode($raw);

    if ($modelClass !== null && is_array($decoded) && class_exists($modelClass)) {
      try {
        // try to instantiate using named args if supported
        return new $modelClass(...$decoded);
      } catch (\Throwable $e) {
        try {
          // fallback: create instance and assign public properties
          $obj = new $modelClass();
          foreach ($decoded as $k => $v) {
            $obj->$k = $v;
          }
          return $obj;
        } catch (\Throwable $e2) {
          // give up and return decoded array
        }
      }
    }

    return $decoded;
  }

  public function delete($key)
  {
    $sql  = 'DELETE FROM config WHERE name = :name';
    $stmt = $this->conn->prepare($sql);
    $stmt->execute([':name' => $key]);
  }

  public function close()
  {
    // PDO doesn't have an explicit close; unset the connection
    $this->conn = null; // @phpstan-ignore-line
  }
}
