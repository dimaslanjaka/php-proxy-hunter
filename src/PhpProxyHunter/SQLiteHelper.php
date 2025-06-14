<?php

namespace PhpProxyHunter;

use PDO;

if (!defined('PHP_PROXY_HUNTER')) {
  exit('access denied');
}

/**
 * Class SQLiteHelper
 *
 * Helper class for interacting with SQLite database.
 *
 * @package PhpProxyHunter
 */
class SQLiteHelper
{
  /** @var PDO|null $pdo */
  public $pdo;

  /** @var PDO[] Static property to hold PDO instances. */
  private static $_databases = [];

  /** @var string The unique key for the current PDO instance. */
  private $uniqueKey;

  /**
   * SQLiteHelper constructor.
   *
   * @param string $dbPath The path to the SQLite database file.
   * @param bool $unique Whether to use a unique key based on caller location.
   */
  public function __construct(string $dbPath, bool $unique = false)
  {
    $trace = debug_backtrace();
    // Unique key is based on the last caller if $unique is true
    $caller = $unique ? end($trace) : $trace[0];
    $callerFile = $caller['file'] ?? 'unknown';
    $callerLine = $caller['line'] ?? 'unknown';
    $this->uniqueKey = md5($dbPath . $callerFile . $callerLine);

    // Avoid multiple PDO instance
    if (isset(self::$_databases[$this->uniqueKey])) {
      $this->pdo = self::$_databases[$this->uniqueKey];
    } else {
      $this->pdo = new PDO("sqlite:$dbPath"); // ;busyTimeout=10000
      // Set how long (in seconds) SQLite will wait if the database is locked
      $this->pdo->setAttribute(PDO::ATTR_TIMEOUT, 10);
      // Enable exceptions for error handling
      $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      self::$_databases[$this->uniqueKey] = $this->pdo;
    }
  }

  /**
   * Closes the database connection.
   */
  public function close(): void
  {
    unset(self::$_databases[$this->uniqueKey]);
    $this->pdo = null;
  }

  /**
   * Creates a table in the database.
   *
   * @param string $tableName The name of the table to create.
   * @param array $columns An array of column definitions.
   */
  public function createTable(string $tableName, array $columns): void
  {
    $columnsString = implode(', ', $columns);
    $sql = "CREATE TABLE IF NOT EXISTS $tableName ($columnsString)";
    $this->pdo->exec($sql);
  }

  /**
   * Inserts a record into the specified table.
   *
   * @param string $tableName The name of the table to insert into.
   * @param array $data An associative array of column names and values.
   * @param bool $insertOrIgnore Optional. Determines whether to use INSERT OR IGNORE or INSERT.
   */
  public function insert(string $tableName, array $data, bool $insertOrIgnore = true): void
  {
    // Ensure the table name is valid (alphanumeric and underscores only)
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $tableName)) {
      throw new \InvalidArgumentException('Invalid table name.');
    }

    $columns = implode(', ', array_keys($data));
    $values = implode(', ', array_fill(0, count($data), '?'));
    $sql = $insertOrIgnore ? "INSERT OR IGNORE" : "INSERT";
    $sql = "$sql INTO $tableName ($columns) VALUES ($values)";

    try {
      $stmt = $this->pdo->prepare($sql);
      $stmt->execute(array_values($data));
    } catch (\PDOException $e) {
      // Handle error appropriately
      throw new \RuntimeException('Failed to insert record: ' . $e->getMessage());
    }
  }

  /**
   * Selects records from the specified table.
   *
   * @param string $tableName The name of the table to select from.
   * @param string $columns The columns to select.
   * @param string|null $where The WHERE clause.
   * @param array $params An array of parameters to bind to the query.
   * @return array An array containing the selected records.
   */
  public function select(string $tableName, string $columns = '*', ?string $where = null, array $params = []): array
  {
    $sql = "SELECT $columns FROM $tableName";
    if ($where) {
      $sql .= " WHERE $where";
    }
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $result ?: [];
  }

  /**
   * Executes a custom SQL query and returns the result.
   *
   * @param string $sql The SQL query to execute.
   * @param array $params An array of parameters to bind to the query.
   * @return array The queried result.
   */
  public function executeCustomQuery(string $sql, array $params = []): array
  {
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $result ?: [];
  }

  /**
   * Counts the records in the specified table.
   *
   * @param string $tableName The name of the table to count records from.
   * @param string|null $where The WHERE clause.
   * @param array $params An array of parameters to bind to the query.
   * @return int The count of records.
   */
  public function count(string $tableName, ?string $where = null, array $params = []): int
  {
    $sql = "SELECT COUNT(*) as count FROM $tableName";
    if ($where) {
      $sql .= " WHERE $where";
    }
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetchColumn();
    return $result !== false ? (int)$result : 0;
  }

  /**
   * Updates records in the specified table.
   *
   * @param string $tableName The name of the table to update.
   * @param array $data An associative array of column names and values to update.
   * @param string $where The WHERE clause.
   * @param array $params An array of parameters to bind to the query.
   */
  public function update(string $tableName, array $data, string $where, array $params = []): void
  {
    $setValues = [];
    $setParams = [];
    foreach ($data as $key => $value) {
      if (empty($value) && $value !== 0) {
        $setValues[] = "$key = NULL";
      } else {
        $setValues[] = "$key = ?";
        $setParams[] = $value;
      }
    }
    $setString = implode(', ', $setValues);
    $sql = "UPDATE $tableName SET $setString WHERE $where";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute(array_merge($setParams, $params));
  }

  /**
   * Deletes records from the specified table.
   *
   * @param string $tableName The name of the table to delete from.
   * @param string $where The WHERE clause.
   * @param array $params An array of parameters to bind to the query.
   */
  public function delete(string $tableName, string $where, array $params = []): void
  {
    $sql = "DELETE FROM $tableName WHERE $where";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
  }

  /**
   * Checks if the database is currently locked.
   *
   * @return bool True if the database is locked, false otherwise.
   */
  public function isDatabaseLocked(): bool
  {
    try {
      // Use a lightweight read query
      $this->pdo->query('SELECT 1');
      return false;
    } catch (\PDOException $e) {
      // Check if the error code indicates the database is locked
      if ((int)$e->getCode() === 5) { // SQLITE_BUSY
        return true;
      }
      // Rethrow for other errors
      throw $e;
    }
  }
}
