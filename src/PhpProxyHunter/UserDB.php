<?php

namespace PhpProxyHunter;

use PDO;
use PDOException;

if (!defined('PHP_PROXY_HUNTER')) {
  exit('access denied');
}

/**
 * Class UserDB
 *
 * Handles database operations for user management.
 *
 * @package PhpProxyHunter
 */
class UserDB
{
  /** @var SQLiteHelper $db */
  public $db;

  /**
   * UserDB constructor.
   *
   * Initializes the database connection and schema.
   *
   * @param string|null $dbLocation Path to the database file. Defaults to the project's database directory.
   */
  public function __construct(?string $dbLocation = null)
  {
    if (!$dbLocation) {
      $dbLocation = __DIR__ . '/../database.sqlite';
    } elseif (!file_exists($dbLocation)) {
      // Extract the directory part from the path
      $directory = dirname($dbLocation);
      // Check if the directory exists and create it if it doesn't
      if (!is_dir($directory)) {
        if (!mkdir($directory, 0755, true)) {
          die("Failed to create directory: $directory\n");
        }
      }
    }
    $this->db = new SQLiteHelper($dbLocation);

    // Initialize the database schema
    $sqlFileContents = file_get_contents(__DIR__ . '/../../assets/database/create.sql');
    $this->db->pdo->exec($sqlFileContents);

    // Fix journal mode to WAL
    $wal_status = $this->db->pdo->query("PRAGMA journal_mode")->fetch()['journal_mode'];
    if ($wal_status != 'wal') {
      $this->db->pdo->exec("PRAGMA journal_mode = WAL;");
    }
  }

  /**
   * Selects a user from the database by email, username, or id.
   *
   * @param mixed $id The email, username, or id of the user.
   * @return array The user data including additional fields, or an empty array if not found.
   */
  public function select($id)
  {
    $id = is_string($id) ? trim($id) : $id;
    $conditions = [
        'email = ?',
        'username = ?',
        'id = ?'
    ];
    // Declare empty result
    $result = [];

    foreach ($conditions as $condition) {
      $result = $this->db->select('auth_user', '*', $condition, [$id]);
      if (!empty($result)) {
        break;
      }
    }
    if (isset($result[0])) {
      $result = $result[0];
    }

    if (!empty($result['id'])) {
      // Merge user fields
      $field = $this->db->select('user_fields', '*', 'user_id = ?', [$result['id']]);
      if (!empty($field)) {
        return array_merge($result, $field[0]);
      }
    }

    return $result;
  }

  /**
   * Updates a user's information in the database.
   *
   * @param mixed $id The email, username, or id of the user to update.
   * @param array $data The data to update.
   */
  public function update($id, array $data)
  {
    $conditions = [
        'email = ?',
        'username = ?'
    ];
    foreach ($conditions as $condition) {
      $select = $this->db->select("auth_user", "*", $condition, [$id]);
      if (!empty($select)) {
        $this->db->update("auth_user", $data, "email = ?", [$select[0]['email']]);
        break;
      }
    }
  }

  public function update_saldo(int $id, $saldo)
  {
    if (is_string($saldo)) {
      $saldo = intval($saldo);
    }
    $select = $this->db->select("user_fields", "*", "user_id = ?", [$id]);
    if (empty($select)) {
      // Insert new column when not exist
      $this->db->insert('user_fields', ['user_id' => $id, 'saldo' => 0]);
    }
    $existing_saldo = intval($this->db->select('user_fields', 'saldo', 'user_id = ?', [$id])[0]['saldo']);
    $sum_saldo = $existing_saldo + $saldo;
    $this->db->update('user_fields', ['saldo' => $sum_saldo], "user_id = ?", [$id]);
    return $this->db->select('user_fields', 'saldo', 'user_id = ?', [$id])[0];
  }

  public function get_saldo(int $id) {
    return $this->db->select('user_fields', 'saldo', 'user_id = ?', [$id])[0]['saldo'];
  }
}
