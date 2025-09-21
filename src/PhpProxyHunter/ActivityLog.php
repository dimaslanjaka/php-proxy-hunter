<?php

declare(strict_types=1);

namespace PhpProxyHunter;

use PDO;
use PDOException;

/**
 * Class ActivityLog
 *
 * Handles logging of user activities to the database.
 * Accepts PDO, SQLiteHelper, MySQLHelper, or CoreDB for database connection.
 *
 * @package PhpProxyHunter
 */
class ActivityLog
{
  public const MYSQL_SCHEMA = <<<SQL
    CREATE TABLE IF NOT EXISTS activity_log (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

        user_id BIGINT UNSIGNED NOT NULL,        -- the user who performed the action
        target_user_id BIGINT UNSIGNED NULL,     -- optional: if the action affects another user (e.g., admin topup)

        action_type ENUM(
            'LOGIN',
            'PACKAGE_CREATE',
            'PACKAGE_UPDATE',
            'PACKAGE_DELETE',
            'PACKAGE_BUY',
            'TOPUP',
            'PAYMENT',
            'REFUND',
            'OTHER'
        ) NOT NULL,

        target_id BIGINT UNSIGNED NULL,          -- id of affected record (e.g., package_id, topup_id, etc.)
        target_type VARCHAR(50) NULL,            -- table/entity name (e.g., 'package', 'payment')

        details JSON NULL,                       -- flexible field for extra info (e.g., { "points": 100, "method": "admin" })

        ip_address VARCHAR(45) NULL,             -- store IPv4/IPv6
        user_agent TEXT NULL,                    -- optional for login/device tracking

        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

        INDEX (user_id),
        INDEX (action_type),
        INDEX (target_id),
        INDEX (created_at)
    );
    SQL;
  public const SQLITE_SCHEMA = <<<SQL
    CREATE TABLE IF NOT EXISTS activity_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,

        user_id INTEGER NOT NULL,         -- the user who performed the action
        target_user_id INTEGER NULL,      -- optional: if the action affects another user

        action_type TEXT NOT NULL CHECK (
            action_type IN (
                'LOGIN',
                'PACKAGE_CREATE',
                'PACKAGE_UPDATE',
                'PACKAGE_DELETE',
                'PACKAGE_BUY',
                'TOPUP',
                'PAYMENT',
                'REFUND',
                'OTHER'
            )
        ),

        target_id INTEGER NULL,           -- id of affected record (e.g., package_id, topup_id, etc.)
        target_type TEXT NULL,            -- table/entity name (e.g., 'package', 'payment')

        details TEXT NULL,                -- JSON stored as TEXT (SQLite has JSON1 extension for querying)

        ip_address TEXT NULL,             -- store IPv4/IPv6 (VARCHAR(45) → TEXT)
        user_agent TEXT NULL,             -- optional for login/device tracking

        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    -- Indexes (SQLite requires explicit CREATE INDEX statements)
    CREATE INDEX idx_activity_log_user_id ON activity_log(user_id);
    CREATE INDEX idx_activity_log_action_type ON activity_log(action_type);
    CREATE INDEX idx_activity_log_target_id ON activity_log(target_id);
    CREATE INDEX idx_activity_log_created_at ON activity_log(created_at);
    SQL;


  /**
   * @var PDO
   */
  private PDO $db;

  /**
   * @var string
   */
  private string $driver;

  /**
   * ActivityLog constructor.
   *
   * @param PDO|SQLiteHelper|MySQLHelper|CoreDB $dbOrHelper
   */
  public function __construct($dbOrHelper)
  {
    if ($dbOrHelper instanceof PDO) {
      $this->db     = $dbOrHelper;
      $this->driver = $dbOrHelper->getAttribute(PDO::ATTR_DRIVER_NAME);
    } elseif (
      (class_exists('PhpProxyHunter\\SQLiteHelper') && $dbOrHelper instanceof \PhpProxyHunter\SQLiteHelper) || (class_exists('PhpProxyHunter\\MySQLHelper') && $dbOrHelper instanceof \PhpProxyHunter\MySQLHelper)
    ) {
      $this->db     = $dbOrHelper->pdo;
      $this->driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
    } elseif (class_exists('PhpProxyHunter\\CoreDB') && $dbOrHelper instanceof \PhpProxyHunter\CoreDB) {
      $this->db     = $dbOrHelper->db->pdo;
      $this->driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
    } else {
      throw new \InvalidArgumentException('Invalid argument for ActivityLog constructor. Must be PDO, SQLiteHelper, MySQLHelper, or CoreDB.');
    }
    // Ensure the table exists
    $this->db->exec($this->driver === 'sqlite' ? self::SQLITE_SCHEMA : self::MYSQL_SCHEMA);
  }

  /**
   * Write a log entry.
   */
  public function log(
    int $userId,
    string $actionType,
    ?int $targetId = null,
    ?string $targetType = null,
    ?int $targetUserId = null,
    ?array $details = null,
    ?string $ipAddress = null,
    ?string $userAgent = null
  ): bool {
    try {
      $stmt = $this->db->prepare(<<<SQL
          INSERT INTO activity_log
              (user_id, target_user_id, action_type, target_id, target_type, details, ip_address, user_agent)
          VALUES
              (:user_id, :target_user_id, :action_type, :target_id, :target_type, :details, :ip_address, :user_agent)
      SQL);

      $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
      $stmt->bindValue(':target_user_id', $targetUserId, $targetUserId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
      $stmt->bindValue(':action_type', $actionType);
      $stmt->bindValue(':target_id', $targetId, $targetId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
      $stmt->bindValue(':target_type', $targetType);
      $stmt->bindValue(':details', $details !== null ? json_encode($details, JSON_UNESCAPED_UNICODE) : null);
      $stmt->bindValue(':ip_address', $ipAddress);
      $stmt->bindValue(':user_agent', $userAgent);

      return $stmt->execute();
    } catch (PDOException $e) {
      error_log('ActivityLog error: ' . $e->getMessage());
      return false;
    }
  }

  /**
   * Fetch recent logs.
   */
  public function recent(int $limit = 50): array
  {
    $stmt = $this->db->prepare('SELECT * FROM activity_log
            ORDER BY created_at DESC
            LIMIT :limit');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
}
