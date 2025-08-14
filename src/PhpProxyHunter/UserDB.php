<?php

namespace PhpProxyHunter;

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
  /**
   * @var SQLiteHelper|MySQLHelper $db Database helper instance (SQLite or MySQL)
   */
  public $db;

  public function __construct(?string $dbLocation = null, string $dbType = 'sqlite', string $host = 'localhost', string $dbname = 'php_proxy_hunter', string $username = 'root', string $password = '', bool $unique = false)
  {
    if ($dbType === 'mysql') {
      $this->mysql($host, $dbname, $username, $password, $unique);
    } else {
      $this->sqlite($dbLocation);
    }
  }

  public function mysql(string $host, string $dbname, string $username, string $password, bool $unique = false)
  {
    $this->db = new MySQLHelper($host, $dbname, $username, $password, $unique);

    // Initialize the database schema
    $sqlFileContents = file_get_contents(__DIR__ . '/assets/mysql-schema.sql');
    $this->db->pdo->exec($sqlFileContents);
  }

  /**
   * UserDB constructor.
   *
   * Initializes the database connection and schema.
   *
   * @param string|null $dbLocation Path to the database file. Defaults to the project's database directory.
   */
  public function sqlite(?string $dbLocation = null)
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
    $sqlFileContents = file_get_contents(__DIR__ . '/assets/sqlite-schema.sql');
    $this->db->pdo->exec($sqlFileContents);

    // Fix journal mode to WAL
    $wal_status = $this->db->pdo->query("PRAGMA journal_mode")->fetch()['journal_mode'];
    if ($wal_status != 'wal') {
      $this->db->pdo->exec("PRAGMA journal_mode = WAL;");
    }
  }

  /**
   * Adds a new user to the database.
   *
   * The method ensures required fields (`username`, `password`, `email`) are present,
   * and sets default values for optional fields (`first_name`, `last_name`, etc.).
   * If validation passes, the user is inserted into the `auth_user` table.
   *
   * @param array $data An associative array containing user data. Expected keys include:
   *                    - username (string)
   *                    - password (string)
   *                    - email (string)
   *                    - first_name (string, optional)
   *                    - last_name (string, optional)
   *                    - date_joined (string, optional, Y-m-d H:i:s format)
   *                    - is_staff (bool, optional)
   *                    - is_active (bool, optional)
   *                    - is_superuser (bool, optional)
   *                    - last_login (string|null, optional)
   *
   * @return bool Returns true if the user was successfully added, false otherwise.
   */
  public function add($data)
  {
    // Set mandatory fields with defaults or validation
    $data['username'] = $data['username'] ?? '';
    $data['password'] = $data['password'] ?? '';
    $data['email'] = $data['email'] ?? '';

    // Set optional fields with sensible defaults
    $data['first_name'] = $data['first_name'] ?? '';
    $data['last_name'] = $data['last_name'] ?? '';
    $data['date_joined'] = $data['date_joined'] ?? date('Y-m-d H:i:s');
    $data['is_staff'] = isset($data['is_staff']) ? self::normalizeBoolToInt($data['is_staff']) : 0;
    $data['is_active'] = isset($data['is_active']) ? self::normalizeBoolToInt($data['is_active']) : 1;
    $data['is_superuser'] = isset($data['is_superuser']) ? self::normalizeBoolToInt($data['is_superuser']) : 0;
    $data['last_login'] = $data['last_login'] ?? null;

    // Validate required fields
    if (!empty($data['username']) && !empty($data['password']) && !empty($data['email'])) {
      $this->db->insert("auth_user", $data, true);
      return true;
    }

    return false;
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
    $id = is_string($id) ? trim($id) : $id;
    $conditions = [
      'email = ?',
      'username = ?',
      'id = ?'
    ];
    $success = false;
    foreach ($conditions as $condition) {
      $select = $this->db->select("auth_user", "*", $condition, [$id]);
      if (!empty($select)) {
        $success = true;
        // Update the user data using the correct identifier
        $this->db->update("auth_user", $data, "id = ?", [$select[0]['id']]);
        break;
      }
    }
    return $success;
  }

  /**
   * @param string $log_source log source identifier for logs, eg: refill_saldo, buy_package
   */
  public function update_saldo(int $id, $amount, string $log_source, string $log_extra_info = "")
  {
    $amount = intval($amount);

    $find_existing_row = $this->db->select("user_fields", "*", "user_id = ?", [$id]);
    if (empty($find_existing_row)) {
      // Insert new column when not exist
      $this->db->insert('user_fields', ['user_id' => $id, 'saldo' => 0]);
      $existing_saldo = 0;
    } else {
      $saldo_row = $this->db->select('user_fields', 'saldo', 'user_id = ?', [$id]);
      $existing_saldo = isset($saldo_row[0]['saldo']) ? intval($saldo_row[0]['saldo']) : 0;
    }
    $sum_saldo = $existing_saldo + $amount;
    $this->db->update('user_fields', ['saldo' => $sum_saldo], "user_id = ?", [$id]);

    // Update logs
    $this->db->insert("user_logs", ["message" => "Topup $amount", "log_level" => "INFO", "source" => $log_source, "extra_info" => $log_extra_info, 'user_id' => $id], false);

    $saldo_row = $this->db->select('user_fields', 'saldo', 'user_id = ?', [$id]);
    return isset($saldo_row[0]) ? $saldo_row[0] : ['saldo' => 0];
  }

  public function get_saldo(int $id)
  {
    $saldo_row = $this->db->select('user_fields', 'saldo', 'user_id = ?', [$id]);
    return isset($saldo_row[0]['saldo']) ? $saldo_row[0]['saldo'] : 0;
  }
  /**
   * Normalize boolean-like values to integer (0 or 1).
   * Accepts bool, int, string ('true', 'false', '1', '0', 'yes', 'no', 'on', 'off').
   *
   * @param mixed $value
   * @return int
   */
  private static function normalizeBoolToInt($value): int
  {
    if (is_bool($value)) {
      return $value ? 1 : 0;
    }
    if (is_int($value)) {
      return ($value === 1) ? 1 : 0;
    }
    if (is_string($value)) {
      $v = strtolower(trim($value));
      if (in_array($v, ['1', 'true', 'yes', 'on', 'admin'], true)) {
        return 1;
      }
      if (in_array($v, ['0', 'false', 'no', 'off', '', 'user'], true)) {
        return 0;
      }
    }
    // fallback: treat as falsy
    return 0;
  }

  public function __destruct()
  {
    // Close the database connection
    $this->db->close();
  }

  public function close()
  {
    $this->db->close();
  }
}
