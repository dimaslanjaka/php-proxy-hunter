<?php

namespace PhpProxyHunter;

use PDO;
use PDOException;

class ActivityLog
{
  public const SQL = <<<SQL
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

  private PDO $db;

  public function __construct(PDO $db)
  {
    $this->db = $db;
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
