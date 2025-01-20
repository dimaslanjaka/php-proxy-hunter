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
 * @package PhpProxyHunter
 */
class UserDB
{
  /** @var SQLiteHelper $db */
  public $db;

  /**
   * UserDB constructor.
   *
   * @param string|null $dbLocation
   */
  public function __construct(?string $dbLocation = null)
  {
    if (!$dbLocation) {
      $dbLocation = __DIR__ . '/../database.sqlite';
    } else if (!file_exists($dbLocation)) {
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
  }

  /**
   * Select a user from the database by email, username, or id.
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

    if (!empty($result)) {
      // Merge user fields
      $field = $this->db->select('user_fields', '*', 'id = ?', [$result['id']]);
      return array_merge($result, $field);
    }

    return $result;
  }
}
