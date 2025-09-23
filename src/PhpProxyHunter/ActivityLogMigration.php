<?php

namespace PhpProxyHunter;

require_once __DIR__ . '/const.php';

use PDO;
use PDOException;

/**
 * Class ActivityLogMigration
 * Handles migration for the activity_log table's action_type column.
 */


class ActivityLogMigration
{
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
  public function __construct($pdo)
  {
    $this->pdo    = $pdo;
    $this->driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $this->meta   = new Meta($pdo);
  }

  /**
   * Close the PDO connection.
   */
  public function close()
  {
    if ($this->meta) {
      $this->meta->close();
      $this->meta = null;
    }
    $this->pdo = null;
  }

  /**
   * Destructor to ensure PDO connection is closed.
   */
  public function __destruct()
  {
    $this->close();
  }

  /**
   * Run the migrations.
   */
  public function run()
  {
    $this->migrateActionType();
  }

  /**
   * Run the migration for the action_type column in activity_log table.
   *
   * @param string $packageVersion
   */
  public function migrateActionType()
  {
    $metaKey = 'activity_log_migrated_action_type_' . $this->driver . '_' . PACKAGE_VERSION;
    if ($this->meta->hasKey($metaKey)) {
      return; // Migration already done
    }
    $this->meta->set($metaKey, '1');
    $sql = '';

    if ($this->driver === 'sqlite') {
      // SQLite migration logic (if needed)
    } else {
      $sql = <<<SQL
ALTER TABLE `activity_log` CHANGE `action_type` `action_type` ENUM('LOGIN','PACKAGE_CREATE','PACKAGE_UPDATE','PACKAGE_DELETE','PACKAGE_BUY','TOPUP','OTHER') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL;
SQL;
    }
    if (!empty($sql)) {
      try {
        $this->pdo->exec($sql);
      } catch (PDOException $e) {
        error_log('ActivityLog migration error: ' . $e->getMessage());
      }
    }
  }
}
