<?php

namespace PhpProxyHunter;

use PDO;

class LogsRepository
{
  /** @var \PDO */
  private $pdo;

  /** @var string|null */
  private $driver;

  /**
   * @param \PDO $db
   */
  public function __construct($pdo)
  {
    $this->pdo = $pdo;
    // Get the driver name (e.g., "mysql", "sqlite", "pgsql")
    $this->driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $this->ensureTable();
  }

  private function ensureTable()
  {
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

      CREATE TABLE IF NOT EXISTS `user_activity` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `activity_type` VARCHAR(50) NOT NULL, -- LOGIN, CREATE, UPDATE, DELETE, LOGOUT
        `target_type` VARCHAR(50), -- table/entity name, e.g., "order"
        `target_id` INT, -- entity ID
        `ip_address` VARCHAR(45), -- IPv4/IPv6
        `user_agent` TEXT, -- browser/app info
        `timestamp` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `details` JSON, -- structured info (before/after values)
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

      CREATE TABLE IF NOT EXISTS "user_activity" (
        "id" INTEGER PRIMARY KEY AUTOINCREMENT,
        "user_id" INTEGER NOT NULL REFERENCES "auth_user" ("id"),
        "activity_type" TEXT NOT NULL,
        "target_type" TEXT,
        "target_id" INTEGER,
        "ip_address" TEXT,
        "user_agent" TEXT,
        "timestamp" DATETIME DEFAULT CURRENT_TIMESTAMP,
        "details" TEXT -- JSON string, use json_extract() if SQLite compiled with JSON1
      );
      SQL;
    }
    $this->pdo->exec($sql);
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
      $jsonValue = $extraInfo;
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
   * Inserts a user action into the user_activity table (audit trail).
   *
   * @param int         $userId       The user performing the action.
   * @param string      $activityType Type of activity (LOGIN, CREATE, UPDATE, DELETE, LOGOUT).
   * @param string|null $targetType   Type of entity affected (e.g., "order", "profile").
   * @param int|null    $targetId     ID of the entity affected.
   * @param string|null $ipAddress    IP address of the user.
   * @param string|null $userAgent    User agent string of the client.
   * @param mixed       $details      Extra details (string or array/object as JSON).
   * @return bool True on success, false on failure.
   */
  public function addActivity($userId, $activityType, $targetType = null, $targetId = null, $ipAddress = null, $userAgent = null, $details = null)
  {
    $sql = 'INSERT INTO user_activity
                   (user_id, activity_type, target_type, target_id, ip_address, user_agent, details)
                VALUES
                   (:user_id, :activity_type, :target_type, :target_id, :ip_address, :user_agent, :details)';

    $stmt = $this->pdo->prepare($sql);
    return $stmt->execute([
      ':user_id'       => $userId,
      ':activity_type' => $activityType,
      ':target_type'   => $targetType,
      ':target_id'     => $targetId,
      ':ip_address'    => $ipAddress,
      ':user_agent'    => $userAgent,
      ':details'       => is_array($details) || is_object($details)
        ? json_encode($details, JSON_UNESCAPED_UNICODE)
        : $details,
    ]);
  }

  /**
   * Retrieves recent application/system logs from the user_logs table.
   *
   * @param int $limit Maximum number of logs to retrieve.
   * @return array The list of logs as associative arrays.
   */
  public function getLogsFromDb($limit = 50)
  {
    $sql  = 'SELECT * FROM user_logs ORDER BY timestamp DESC LIMIT :limit';
    $stmt = $this->pdo->prepare($sql);
    $stmt->bindValue(':limit', (int)$limit, \PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(\PDO::FETCH_ASSOC);
  }

  /**
   * Retrieves recent user activities from the user_activity table.
   *
   * @param int $limit Maximum number of activities to retrieve.
   * @return array The list of activities as associative arrays.
   */
  public function getActivities($limit = 50)
  {
    $sql  = 'SELECT * FROM user_activity ORDER BY timestamp DESC LIMIT :limit';
    $stmt = $this->pdo->prepare($sql);
    $stmt->bindValue(':limit', (int)$limit, \PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(\PDO::FETCH_ASSOC);
  }
}
