<?php

namespace PhpProxyHunter;

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

  /**
   * @var LogsRepository $logsRepository Logs repository instance
   */
  public $logsRepository;

  /**
   * UserDB constructor.
   *
   * @param string|SQLiteHelper|MySQLHelper|null $dbLocation Path to the database file (SQLite mode).
   * @param string $dbType Database type: 'sqlite' or 'mysql'.
   * @param string $host MySQL host.
   * @param string $dbname MySQL database name.
   * @param string $username MySQL username.
   * @param string $password MySQL password.
   * @param bool $unique Whether to enforce unique constraints (MySQL only).
   */
  public function __construct($dbLocation = null, $dbType = 'sqlite', $host = 'localhost', $dbname = 'php_proxy_hunter', $username = 'root', $password = '', $unique = false)
  {
    if ($dbLocation instanceof SQLiteHelper || $dbLocation instanceof MySQLHelper) {
      // $dbLocation is an instance of SQLiteHelper or MySQLHelper
      $this->db = $dbLocation;
      // Auto-detect driver type from the DB helper instance
      if ($dbLocation instanceof MySQLHelper) {
        $dbType = 'mysql';
      } else {
        $dbType = 'sqlite';
      }
    } else {
      // $dbLocation is a string (path) or null
      if ($dbType === 'mysql') {
        $this->mysql($host, $dbname, $username, $password, $unique);
      } else {
        $this->sqlite($dbLocation);
      }
    }

    // Use detected driver type for table creation
    if ($dbType === 'mysql') {
      $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS `auth_user` (
      `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
      `password` VARCHAR(128) NOT NULL,
      `last_login` DATETIME NULL,
      `is_superuser` TINYINT(1) NOT NULL,
      `username` VARCHAR(150) NOT NULL UNIQUE,
      `last_name` VARCHAR(150) NOT NULL,
      `email` VARCHAR(254) NOT NULL,
      `is_staff` TINYINT(1) NOT NULL,
      `is_active` TINYINT(1) NOT NULL,
      `date_joined` DATETIME NOT NULL,
      `first_name` VARCHAR(150) NOT NULL
    );
    SQL;
    } else {
      $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS "auth_user" (
      "id" INTEGER PRIMARY KEY AUTOINCREMENT,
      "password" TEXT NOT NULL,
      "last_login" TEXT,            -- ISO8601 string
      "is_superuser" INTEGER NOT NULL,
      "username" TEXT NOT NULL UNIQUE,
      "last_name" TEXT NOT NULL,
      "email" TEXT NOT NULL,
      "is_staff" INTEGER NOT NULL,
      "is_active" INTEGER NOT NULL,
      "date_joined" TEXT NOT NULL,
      "first_name" TEXT NOT NULL
    );
    SQL;
    }

    $this->db->pdo->exec(trim($sql));

    $this->logsRepository = new LogsRepository($this->db->pdo);
  }

  /**
   * Connect to MySQL database and initialize schema.
   *
   * @param string $host MySQL host.
   * @param string $dbname Database name.
   * @param string $username Username.
   * @param string $password Password.
   * @param bool $unique Whether to enforce uniqueness.
   */
  public function mysql($host, $dbname, $username, $password, $unique = false)
  {
    $this->db = new MySQLHelper($host, $dbname, $username, $password, $unique);

    $sqlFileContents = file_get_contents(__DIR__ . '/assets/mysql-schema.sql');
    $this->db->pdo->exec($sqlFileContents);
  }

  /**
   * Connect to SQLite database and initialize schema.
   *
   * @param string|null $dbLocation Path to the database file. Defaults to the project's database directory.
   */
  public function sqlite($dbLocation = null)
  {
    if (!$dbLocation) {
      $dbLocation = __DIR__ . '/../database.sqlite';
    } elseif (!file_exists($dbLocation)) {
      $directory = dirname($dbLocation);
      if (!is_dir($directory)) {
        if (!mkdir($directory, 0755, true)) {
          die("Failed to create directory: $directory\n");
        }
      }
    }
    $this->db = new SQLiteHelper($dbLocation);

    $sqlFileContents = file_get_contents(__DIR__ . '/assets/sqlite-schema.sql');
    $this->db->pdo->exec($sqlFileContents);

    $wal_status = $this->db->pdo->query('PRAGMA journal_mode')->fetch()['journal_mode'];
    if ($wal_status != 'wal') {
      $this->db->pdo->exec('PRAGMA journal_mode = WAL;');
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
    $data['username'] = isset($data['username']) ? $data['username'] : '';
    $data['password'] = isset($data['password']) ? $data['password'] : '';
    $data['email']    = isset($data['email']) ? $data['email'] : '';

    $data['first_name']   = isset($data['first_name']) ? $data['first_name'] : '';
    $data['last_name']    = isset($data['last_name']) ? $data['last_name'] : '';
    $data['date_joined']  = isset($data['date_joined']) ? $data['date_joined'] : date('Y-m-d H:i:s');
    $data['is_staff']     = isset($data['is_staff']) ? self::normalizeBoolToInt($data['is_staff']) : 0;
    $data['is_active']    = isset($data['is_active']) ? self::normalizeBoolToInt($data['is_active']) : 1;
    $data['is_superuser'] = isset($data['is_superuser']) ? self::normalizeBoolToInt($data['is_superuser']) : 0;
    $data['last_login']   = isset($data['last_login']) ? $data['last_login'] : null;

    if (!empty($data['username']) && !empty($data['password']) && !empty($data['email'])) {
      $this->db->insert('auth_user', $data, true);
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
    $id         = is_string($id) ? trim($id) : $id;
    $conditions = ['email = ?', 'username = ?', 'id = ?'];
    $result     = [];

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
   * @return bool True if update successful, false otherwise.
   */
  public function update($id, array $data)
  {
    $id         = is_string($id) ? trim($id) : $id;
    $conditions = ['email = ?', 'username = ?', 'id = ?'];
    $success    = false;

    foreach ($conditions as $condition) {
      $select = $this->db->select('auth_user', '*', $condition, [$id]);
      if (!empty($select)) {
        $success = true;
        $this->db->update('auth_user', $data, 'id = ?', [$select[0]['id']]);
        break;
      }
    }
    return $success;
  }

  /**
   * Updates a user's saldo (balance) in the database.
   *
   * If $replace is true, the saldo is set to $amount. If false, $amount is added to the current saldo.
   * Also logs the operation using the LogsRepository.
   *
   * @param int         $id             User ID.
   * @param int         $amount         Amount to add or set.
   * @param string      $log_source     Log source identifier (e.g. 'refill_saldo', 'buy_package').
   * @param string|array|null $log_extra_info Extra info for logs (optional).
   * @param bool        $replace        If true, replace saldo with $amount; if false, increment by $amount (default: false).
   *
   * @return array Returns an array with the updated saldo, e.g. ['saldo' => int].
   */
  public function updatePoint($id, $amount, $log_source, $log_extra_info = '', $replace = false)
  {
    // Get current saldo (if exists)
    $saldo_row      = $this->db->select('user_fields', 'saldo', 'user_id = ?', [$id]);
    $existing_saldo = isset($saldo_row[0]['saldo']) ? (int)$saldo_row[0]['saldo'] : 0;

    // If no record, insert initial row
    if (empty($saldo_row)) {
      $this->db->insert('user_fields', ['user_id' => $id, 'saldo' => 0]);
    }

    // Calculate new saldo
    $new_saldo = $replace ? (int)$amount : $existing_saldo + (int)$amount;

    // Update saldo
    $this->db->update('user_fields', ['saldo' => $new_saldo], 'user_id = ?', [$id]);

    // Prepare log
    $log_action = $replace ? "Set saldo to {$amount}" : "Topup {$amount}";
    $extraInfo  = $log_extra_info;

    if (is_array($extraInfo) || is_object($extraInfo)) {
      $extraInfo = json_encode($extraInfo, JSON_UNESCAPED_UNICODE);
    } elseif ($extraInfo === null || $extraInfo === '') {
      $extraInfo = null;
    }

    // Insert log
    // $this->db->insert('user_logs', [
    //   'message'    => $log_action,
    //   'log_level'  => 'INFO',
    //   'source'     => $log_source,
    //   'extra_info' => $extraInfo,
    //   'user_id'    => $id,
    // ], false);
    $this->logsRepository->addLog($id, $log_action, 'INFO', $log_source, $extraInfo);

    return ['saldo' => $new_saldo];
  }

  /**
   * Get user's point (saldo/balance).
   *
   * @param int $id User ID.
   * @return int Current point (saldo) value.
   */
  public function getPoint($id)
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
  private static function normalizeBoolToInt($value)
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
    return 0;
  }

  /**
   * Deletes a user from the database by email, username, or id.
   *
   * This method removes the user from the `auth_user` table and also deletes
   * all related records from `user_discount`, `user_logs`, and `user_fields` tables.
   *
   * @param mixed $id The email, username, or id of the user to delete.
   * @return bool True if the user was found and deleted, false otherwise.
   */
  public function delete($id)
  {
    $id         = is_string($id) ? trim($id) : $id;
    $conditions = ['email = ?', 'username = ?', 'id = ?'];
    $success    = false;

    foreach ($conditions as $condition) {
      $select = $this->db->select('auth_user', '*', $condition, [$id]);
      if (!empty($select)) {
        $success = true;
        // $this->db->delete('auth_user', 'id = ?', [$select[0]['id']]);
        $ids = implode(',', array_map('intval', [$select[0]['id']]));
        // Delete from user_activity first to avoid FK constraint errors
        $this->db->pdo->exec("DELETE FROM user_activity WHERE user_id IN ($ids)");
        $this->db->pdo->exec("DELETE FROM user_discount WHERE user_id IN ($ids)");
        $this->db->pdo->exec("DELETE FROM user_logs WHERE user_id IN ($ids)");
        $this->db->pdo->exec("DELETE FROM user_fields WHERE user_id IN ($ids)");
        $this->db->pdo->exec("DELETE FROM auth_user WHERE id IN ($ids)");
        break;
      }
    }
    return $success;
  }

  /**
   * Destructor: closes DB connection.
   */
  public function __destruct()
  {
    $this->db->close();
  }

  /**
   * Manually close DB connection.
   */
  public function close()
  {
    $this->db->close();
  }
}
