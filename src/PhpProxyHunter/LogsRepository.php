<?php

namespace PhpProxyHunter;

require_once __DIR__ . '/shim.php';

use PDO;

class LogsRepository
{
  /** @var \PDO */
  private $pdo;

  /** @var string|null */
  private $driver;

  /** @var \PhpProxyHunter\MySQLHelper|\PhpProxyHunter\SQLiteHelper|null */
  private $helper = null;

  /**
   * @param \PDO|\PhpProxyHunter\CoreDB $db
   */
  public function __construct($db)
  {
    if ($db instanceof CoreDB) {
      $pdo          = $db->db->pdo;
      $this->driver = $db->driver;
      $this->helper = $db->db;
    } elseif ($db instanceof PDO) {
      $pdo = $db;
      // Get the driver name (e.g., "mysql", "sqlite", "pgsql")
      $this->driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
      if ($this->driver === 'sqlite') {
        $this->helper = new SQLiteHelper($pdo);
      } elseif ($this->driver === 'mysql') {
        $this->helper = new MySQLHelper($pdo);
      }
    } else {
      throw new \InvalidArgumentException('Expected instance of PDO or CoreDB.');
    }
    $this->pdo = $pdo;

    $this->ensureTable();
  }

  private function ensureTable()
  {
    // Check if table auth_user exists, throw if not
    try {
      $this->pdo->query('SELECT 1 FROM `auth_user` LIMIT 1');
    } catch (\PDOException $e) {
      throw new \Exception("Required table 'auth_user' does not exist in the database.");
    }

    if ($this->driver === 'mysql') {
      $sql = <<<SQL
      CREATE TABLE IF NOT EXISTS `user_logs` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `timestamp` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `log_level` VARCHAR(32) NOT NULL DEFAULT 'INFO', -- INFO, WARNING, ERROR
        `message` TEXT NOT NULL, -- human-readable log
        `source` VARCHAR(255), -- module/service
        `extra_info` JSON, -- use JSON if possible
        FOREIGN KEY (`user_id`) REFERENCES `auth_user` (`id`)
      ) ENGINE = InnoDB;
      SQL;
    } else {
      $sql = <<<SQL
      CREATE TABLE IF NOT EXISTS "user_logs" (
        "id" INTEGER PRIMARY KEY AUTOINCREMENT,
        "user_id" INTEGER NOT NULL REFERENCES "auth_user" ("id"),
        "timestamp" DATETIME DEFAULT CURRENT_TIMESTAMP,
        "log_level" TEXT NOT NULL DEFAULT 'INFO',
        "message" TEXT NOT NULL,
        "source" TEXT,
        "extra_info" TEXT -- store JSON as TEXT, queryable with JSON1
      );
      SQL;
    }
    $this->pdo->exec($sql);

    $migration = new LogsRepositoryMigrations($this->pdo, $this->driver);
    $migration->migrateIfNeeded();
  }

  /**
   * Write log content to a file by its hash.
   *
   * @param string $hash    The hash identifying the log file.
   * @param string $content The log content to write.
   * @return bool True on success, false on failure.
   * @throws \Exception If required helper functions are not defined or file path cannot be determined.
   */
  public function addLogByHash($hash, $content)
  {
    $file = $this->getLogFilePath($hash);
    if ($file === null) {
      throw new \Exception('Could not determine log file path. Make sure tmp() is defined.');
    }
    if (!function_exists('write_file')) {
      throw new \Exception('Function write_file() is not defined. Make sure to include the necessary files.');
    }
    if (is_array($content) || is_object($content)) {
      $content = json_encode($content, JSON_UNESCAPED_UNICODE);
    }
    return write_file($file, $content);
  }

  /**
   * Retrieves the log content by its hash.
   *
   * @param string $hash The hash identifying the log file.
   * @return string|null The log content if found, or null if not found.
   * @throws \Exception If required helper functions are not defined.
   */
  public function getLogsByHash($hash)
  {
    $file = $this->getLogFilePath($hash);
    if ($file === null) {
      return null;
    }
    if (!function_exists('read_file')) {
      throw new \Exception('Function read_file() is not defined. Make sure to include the necessary files.');
    }
    return read_file($file) ?: null;
  }

  /**
   * Get the file path for a log file by hash. Returns null if tmp() is not defined.
   *
   * @param string $hash
   * @return string|null
   */
  public function getLogFilePath($hash)
  {
    if (!function_exists('tmp')) {
      throw new \Exception('Function tmp() is not defined. Make sure to include the necessary files.');
    }
    $file1 = tmp() . '/logs/' . $hash . '.txt';
    $file2 = tmp() . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . $hash . '.txt';
    if (file_exists($file1)) {
      return $file1;
    }
    if (file_exists($file2)) {
      return $file2;
    }
    // If neither exists, return the first path as the default for writing
    return $file1;
  }

  /**
   * Inserts an application/system log into the user_logs table.
   *
   * @param int         $userId    The user ID related to the log entry.
   * @param string      $message   The log message.
   * @param string      $logLevel  Log severity (INFO, WARNING, ERROR).
   * @param string|null $source    Source module/component of the log.
   * @param mixed       $extraInfo Extra info (string or array/object to be stored as JSON).
   * @return bool True on success, false on failure.
   */
  public function addLog($userId, $message, $logLevel = 'INFO', $source = null, $extraInfo = null)
  {
    $sql = 'INSERT INTO user_logs (user_id, log_level, message, source, extra_info)
                VALUES (:user_id, :log_level, :message, :source, :extra_info)';

    $stmt      = $this->pdo->prepare($sql);
    $jsonValue = null;
    if (is_array($extraInfo) || is_object($extraInfo)) {
      $jsonValue = json_encode($extraInfo, JSON_UNESCAPED_UNICODE);
    } elseif ($extraInfo === null || $extraInfo === '') {
      // For MySQL JSON columns, null is valid, empty string is not
      $jsonValue = null;
    } else {
      // Use json_validate if available to check if $extraInfo is valid JSON
      if (function_exists('json_validate') && \json_validate($extraInfo)) {
        $jsonValue = $extraInfo;
      } else {
        $jsonValue = json_encode($extraInfo, JSON_UNESCAPED_UNICODE);
      }
    }
    return $stmt->execute([
      ':user_id'    => $userId,
      ':log_level'  => $logLevel,
      ':message'    => $message,
      ':source'     => $source,
      ':extra_info' => $jsonValue,
    ]);
  }

  /**
   * Retrieves paginated application/system logs from the user_logs and package_logs tables.
   *
   * @param int $limit  Maximum number of logs to retrieve per page.
   * @param int $offset Offset for pagination (number of logs to skip).
   * @return array The list of logs as associative arrays.
   */
  public function getLogsFromDb($limit = 50, $offset = 0)
  {
    $logs = [];

    // Collect logs from user_logs with user info
    if ($this->helper->hasTable('user_logs') && $this->helper->hasTable('auth_user')) {
      $sql = 'SELECT ul.*, ul.`timestamp` AS log_time, u.username AS user_username, u.email AS user_email, u.id AS user_id_real
              FROM `user_logs` ul
              LEFT JOIN `auth_user` u ON ul.user_id = u.id
              ORDER BY ul.`timestamp` DESC
              LIMIT :limit OFFSET :offset';
      $stmt = $this->pdo->prepare($sql);
      $stmt->bindValue(':limit', (int)$limit, \PDO::PARAM_INT);
      $stmt->bindValue(':offset', (int)$offset, \PDO::PARAM_INT);
      $stmt->execute();
      $userLogs = $stmt->fetchAll(\PDO::FETCH_ASSOC);
      $logs     = array_merge($logs, $userLogs);
    }

    // Collect logs from package_logs with package info
    if ($this->helper->hasTable('package_logs') && $this->helper->hasTable('packages')) {
      $sql = 'SELECT pl.*, pl.`created_at` AS log_time, p.name AS package_name, p.id AS package_id_real
              FROM `package_logs` pl
              LEFT JOIN `packages` p ON pl.package_id = p.id
              ORDER BY pl.`created_at` DESC
              LIMIT :limit OFFSET :offset';
      $stmt = $this->pdo->prepare($sql);
      $stmt->bindValue(':limit', (int)$limit, \PDO::PARAM_INT);
      $stmt->bindValue(':offset', (int)$offset, \PDO::PARAM_INT);
      $stmt->execute();
      $packageLogs = $stmt->fetchAll(\PDO::FETCH_ASSOC);
      $logs        = array_merge($logs, $packageLogs);
    }

    // Sort all logs by log_time descending
    usort($logs, function ($a, $b) {
      $timeA = isset($a['log_time']) ? strtotime($a['log_time']) : 0;
      $timeB = isset($b['log_time']) ? strtotime($b['log_time']) : 0;
      return $timeB <=> $timeA;
    });

    // Slice to $limit entries for combined pagination
    $logs = array_slice($logs, 0, $limit);
    return $logs;
  }
}
