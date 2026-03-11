<?php

namespace PhpProxyHunter;

use PDO;
use PDOException;

/**
 * Class ProxyDBMigration
 * Adds `tun2socks` column to `proxies` table for both SQLite and MySQL.
 */
class ProxyDBMigration
{
  /** @var PDO */
  protected $pdo;

  /** @var string */
  protected $driver;

  /** @var Meta */
  protected $meta;

  public function __construct($pdo)
  {
    $this->pdo    = $pdo;
    $this->driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $this->meta   = new Meta($pdo);
  }

  public function close()
  {
    if ($this->meta) {
      $this->meta->close();
      $this->meta = null;
    }
    $this->pdo = null;
  }

  public function __destruct()
  {
    $this->close();
  }

  public function run()
  {
    $this->migrateAddTun2socks();
  }

  protected function migrateAddTun2socks()
  {
    $metaKey = 'proxy_db_added_tun2socks_' . $this->driver . '_' . PACKAGE_VERSION;
    if ($this->meta->hasKey($metaKey)) {
      return;
    }

    try {
      if ($this->driver === 'sqlite') {
        // Check if column exists
        $stmt   = $this->pdo->query("PRAGMA table_info('proxies')");
        $cols   = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $exists = false;
        foreach ($cols as $col) {
          if (isset($col['name']) && $col['name'] === 'tun2socks') {
            $exists = true;
            break;
          }
        }
        if (!$exists) {
          $this->pdo->exec('ALTER TABLE "proxies" ADD COLUMN "tun2socks" TEXT NULL');
        }
      } else {
        // MySQL: inspect columns
        $col = $this->pdo->query("SHOW COLUMNS FROM `proxies` LIKE 'tun2socks'")->fetch(PDO::FETCH_ASSOC);
        if (!$col) {
          $sql = 'ALTER TABLE `proxies` ADD COLUMN `tun2socks` VARCHAR(255) NULL';
          $this->pdo->exec($sql);
        }
      }

      if (!$this->meta->hasKey($metaKey)) {
        $this->meta->set($metaKey, '1');
      }
    } catch (PDOException $e) {
      error_log('ProxyDB migration error: ' . $e->getMessage());
    }
  }
}
