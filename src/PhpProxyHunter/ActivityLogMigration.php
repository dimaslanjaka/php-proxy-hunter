<?php

namespace PhpProxyHunter;

require_once __DIR__ . '/const.php';

use PDO;
use PDOException;

/**
 * Class ActivityLogMigration
 * Handles migration for the activity_log table's action_type column.
 */


class ActivityLogMigration {
  /**
   * @var PDO
   */
  protected $pdo;

  /**
   * @var string
   */
  protected $driver;

  /**
   * @var Meta
   */
  protected $meta;

  /**
   * ActivityLogMigration constructor.
   * @param PDO $pdo
   */
  public function __construct($pdo) {
    $this->pdo    = $pdo;
    $this->driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $this->meta   = new Meta($pdo);
  }

  /**
   * Close the PDO connection.
   */
  public function close() {
    if ($this->meta) {
      $this->meta->close();
      $this->meta = null;
    }
    $this->pdo = null;
  }

  /**
   * Destructor to ensure PDO connection is closed.
   */
  public function __destruct() {
    $this->close();
  }

  /**
   * Run the migrations.
   */
  public function run() {
    $this->migrateActionType();
  }

  /**
   * Run the migration for the action_type column in activity_log table.
   *
   * @param string $packageVersion
   */
  public function migrateActionType() {
    $metaKey = 'activity_log_migrated_action_type_' . $this->driver . '_' . PACKAGE_VERSION;
    // Always inspect current column type and ensure required enum values exist.
    if ($this->driver === 'sqlite') {
      // SQLite migration logic (none needed for enum-like text)
      if (!$this->meta->hasKey($metaKey)) {
        $this->meta->set($metaKey, '1');
      }
      return;
    }

    try {
      // Inspect current column definition
      $col        = $this->pdo->query("SHOW COLUMNS FROM `activity_log` WHERE Field = 'action_type'")->fetch(PDO::FETCH_ASSOC);
      $needsAlter = true;
      if ($col && isset($col['Type'])) {
        $type = $col['Type'];
        // If PAYMENT and REFUND already present, nothing to do
        if (strpos($type, "'PAYMENT'") !== false && strpos($type, "'REFUND'") !== false) {
          $needsAlter = false;
        }
      }

      if ($needsAlter) {
        $sql = <<<SQL
ALTER TABLE `activity_log` CHANGE `action_type` `action_type` ENUM('LOGIN','PACKAGE_CREATE','PACKAGE_UPDATE','PACKAGE_DELETE','PACKAGE_BUY','TOPUP','PAYMENT','REFUND','OTHER') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL;
SQL;
        $this->pdo->exec($sql);
      }
      // mark migration done
      if (!$this->meta->hasKey($metaKey)) {
        $this->meta->set($metaKey, '1');
      }
    } catch (PDOException $e) {
      error_log('ActivityLog migration error: ' . $e->getMessage());
    }
  }
}
