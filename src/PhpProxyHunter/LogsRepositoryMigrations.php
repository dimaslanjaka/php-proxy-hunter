<?php

namespace PhpProxyHunter;

class LogsRepositoryMigrations
{
  /**
   * @var \PDO
   */
  private $pdo;

  /**
   * @var string 'sqlite'|'mysql'
   */
  private $driver;

  /**
   * @var MySQLHelper|SQLiteHelper
   */
  private $helper;

  /**
   * @var string
   */
  private $migrationKey = 'migration:LogsRepositoryMigrations';

  public function __construct(\PDO $pdo, string $driver)
  {
    $this->pdo    = $pdo;
    $this->driver = $driver;
    if ($driver === 'mysql') {
      $this->helper = new MySQLHelper($pdo);
      $sql          = 'CREATE TABLE IF NOT EXISTS meta (
        `key` VARCHAR(255) PRIMARY KEY,
        `value` TEXT
      ) ENGINE=InnoDB;';
    } else {
      $this->helper = new SQLiteHelper($pdo);
      $sql          = 'CREATE TABLE IF NOT EXISTS meta (
        key TEXT PRIMARY KEY,
        value TEXT
      );';
    }
    $this->pdo->exec($sql);
  }

  /**
   * Run migrations if the migration file checksum has changed.
   */
  public function migrateIfNeeded(): void
  {
    // Get current git commit hash
    try {
      if (function_exists('exec')) {
        $commitHash = trim(exec('git rev-parse --short HEAD'));
      } else {
        $commitHash = '';
      }
    } catch (\Throwable $e) {
      $commitHash = '';
    }
    // Calculate current file checksum
    $file            = __FILE__;
    $currentChecksum = $commitHash . '-' . hash_file('sha256', $file);
    $lastChecksum    = $this->getLastChecksum();
    if ($currentChecksum !== $lastChecksum) {
      $this->run();
      $this->setLastChecksum($currentChecksum);
    }
  }

  /**
   * Get the last stored checksum from the meta table.
   */
  private function getLastChecksum(): ?string
  {
    $sql = $this->driver === 'mysql'
      ? 'SELECT value FROM meta WHERE `key` = :key'
      : 'SELECT value FROM meta WHERE key = :key';
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([':key' => $this->migrationKey]);
    $result = $stmt->fetchColumn();
    return $result === false ? null : $result;
  }

  /**
   * Store the current checksum to the meta table.
   */
  private function setLastChecksum(string $checksum): void
  {
    if ($this->driver === 'sqlite') {
      $this->pdo->prepare(
        'INSERT INTO meta (key, value) VALUES (:key, :value)
        ON CONFLICT(key) DO UPDATE SET value = excluded.value'
      )->execute([
        ':key'   => $this->migrationKey,
        ':value' => $checksum,
      ]);
    } elseif ($this->driver === 'mysql') {
      $this->pdo->prepare(
        'INSERT INTO meta (`key`, `value`) VALUES (:key, :value)
        ON DUPLICATE KEY UPDATE value = VALUES(value)'
      )->execute([
        ':key'   => $this->migrationKey,
        ':value' => $checksum,
      ]);
    }
  }

  private function run(): void
  {
    // Add column log_type to user_logs if it does not exist, values: system,package,payment,other
    if (!$this->helper->columnExists('user_logs', 'log_type')) {
      if ($this->driver === 'mysql') {
        $this->helper->addColumnIfNotExists('user_logs', 'log_type', "ENUM('system', 'package', 'payment', 'other')");
      } elseif ($this->driver === 'sqlite') {
        // SQLite does not support ENUM, use TEXT with CHECK constraint
        $this->helper->addColumnIfNotExists('user_logs', 'log_type', "TEXT CHECK(log_type IN ('system', 'package', 'payment', 'other'))");
      }
    }

    // Add user_id column
    if (!$this->helper->columnExists('user_activity', 'user_id')) {
      $this->helper->addColumnIfNotExists('user_activity', 'user_id', 'INTEGER NOT NULL REFERENCES auth_user(id)');
    }

    // add details column to user_activity if it does not exist
    if ($this->helper->columnExists('user_activity', 'details')) {
      if ($this->driver === 'sqlite') {
        $this->helper->modifyColumnIfExists('user_activity', 'details', 'TEXT DEFAULT NULL');
      } elseif ($this->driver === 'mysql') {
        $this->helper->modifyColumnIfExists('user_activity', 'details', 'TEXT NULL DEFAULT NULL');
      }
    }
  }
}
