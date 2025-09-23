<?php

namespace PhpProxyHunter;

/**
 * Class Meta
 *
 * Handles meta information schema for SQLite and MySQL databases.
 */
class Meta
{
  /**
   * SQLite schema for the meta table.
   *
   * @var string
   */
  public $SQLiteSchema = 'CREATE TABLE IF NOT EXISTS "meta" (key TEXT PRIMARY KEY, value TEXT);';

  /**
   * MySQL schema for the meta table.
   *
   * @var string
   */
  public $MySQLSchema = 'CREATE TABLE IF NOT EXISTS `meta` (key VARCHAR(255) PRIMARY KEY, value TEXT);';

  /**
   * PDO database connection instance.
   *
   * @var \PDO
   */
  public $pdo;

  /**
   * Database driver name.
   *
   * @var string
   */
  public $driver;

  /**
   * Meta constructor.
   *
   * @param \PDO $pdo PDO database connection instance.
   */
  public function __construct($pdo)
  {
    $this->pdo    = $pdo;
    $this->driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
    // Ensure the table exists
    $this->pdo->exec($this->driver === 'sqlite' ? $this->SQLiteSchema : $this->MySQLSchema);
  }

  public function close()
  {
    $this->pdo = null;
  }

  public function __destruct()
  {
    $this->close();
  }

  public function hasKey($key)
  {
    $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM meta WHERE `key` = :key');
    $stmt->execute([':key' => $key]);
    return $stmt->fetchColumn() > 0;
  }

  public function get($key, $default = null)
  {
    $stmt = $this->pdo->prepare('SELECT value FROM meta WHERE `key` = :key');
    $stmt->execute([':key' => $key]);
    return $stmt->fetchColumn() ?: $default;
  }

  public function set($key, $value)
  {
    if ($this->hasKey($key)) {
      $stmt = $this->pdo->prepare('UPDATE meta SET value = :value WHERE `key` = :key');
    } else {
      $stmt = $this->pdo->prepare('INSERT INTO meta (`key`, value) VALUES (:key, :value)');
    }
    return $stmt->execute([':key' => $key, ':value' => $value]);
  }
}
