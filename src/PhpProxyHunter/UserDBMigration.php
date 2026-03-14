<?php

namespace PhpProxyHunter;

require_once __DIR__ . '/const.php';

use PDO;
use PDOException;

/**
 * Class UserDBMigration
 * Adds `token` column to `auth_user` table for both SQLite and MySQL.
 */
class UserDBMigration
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
    $this->migrateAddToken();
  }

  protected function migrateAddToken()
  {
    $metaKey = 'user_db_added_token_' . $this->driver . '_' . PACKAGE_VERSION;
    if ($this->meta->hasKey($metaKey)) {
      return;
    }

    try {
      if ($this->driver === 'sqlite') {
        // Check if column exists
        $stmt   = $this->pdo->query("PRAGMA table_info('auth_user')");
        $cols   = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $exists = false;
        foreach ($cols as $col) {
          if (isset($col['name']) && $col['name'] === 'token') {
            $exists = true;
            break;
          }
        }
        if (!$exists) {
          $this->pdo->exec('ALTER TABLE "auth_user" ADD COLUMN "token" TEXT NULL');
          // Create unique index for token (NULLs are allowed)
          $this->pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_auth_user_token ON auth_user(token)');
        }
        // Populate missing tokens
        $this->populateMissingTokens();
      } else {
        // MySQL: inspect columns
        $col = $this->pdo->query("SHOW COLUMNS FROM `auth_user` LIKE 'token'")->fetch(PDO::FETCH_ASSOC);
        if (!$col) {
          $sql = 'ALTER TABLE `auth_user` ADD COLUMN `token` VARCHAR(64) NULL';
          $this->pdo->exec($sql);
          // Add unique index
          try {
            $this->pdo->exec('CREATE UNIQUE INDEX idx_auth_user_token ON auth_user(token)');
          } catch (PDOException $e) {
            // ignore index creation errors (e.g., duplicates)
          }
        }
        // Populate missing tokens
        $this->populateMissingTokens();
      }

      if (!$this->meta->hasKey($metaKey)) {
        $this->meta->set($metaKey, '1');
      }
    } catch (PDOException $e) {
      $this->meta->delete($metaKey);
      error_log('UserDB migration error: ' . $e->getMessage());
    }
  }

  /**
   * Populate auth_user.token for rows where token is NULL or empty.
   * Ensures tokens are unique; retries on collision a few times.
   */
  protected function populateMissingTokens()
  {
    try {
      $stmt = $this->pdo->query("SELECT id FROM auth_user WHERE token IS NULL OR token = ''");
      $ids  = $stmt->fetchAll(PDO::FETCH_COLUMN);
      if (empty($ids)) {
        return;
      }
      $updateStmt = $this->pdo->prepare('UPDATE auth_user SET token = :token WHERE id = :id');
      foreach ($ids as $id) {
        $attempts = 0;
        while ($attempts < 6) {
          $attempts++;
          try {
            $token = bin2hex(random_bytes(32));
          } catch (\Throwable $e) {
            // fallback
            $token = bin2hex(openssl_random_pseudo_bytes(32));
          }
          try {
            $updateStmt->execute([':token' => $token, ':id' => $id]);
            break; // success
          } catch (PDOException $e) {
            // unique constraint violation or other DB error — retry token
            if ($attempts >= 6) {
              error_log('UserDBMigration: failed to set unique token for user id ' . $id . ': ' . $e->getMessage());
              break;
            }
            // else loop and try another token
          }
        }
      }
    } catch (PDOException $e) {
      error_log('UserDBMigration populateMissingTokens error: ' . $e->getMessage());
    }
  }
}
