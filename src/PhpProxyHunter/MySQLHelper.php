<?php

declare(strict_types=1);

namespace PhpProxyHunter;

use PDO;

if (!defined('PHP_PROXY_HUNTER')) {
  exit('access denied');
}

/**
 * Class MySQLHelper
 *
 * Helper class for interacting with MySQL database.
 *
 * @package PhpProxyHunter
 */
class MySQLHelper
{
  /** @var PDO|null $pdo */
  public $pdo;

  /** @var PDO[] Static property to hold PDO instances. */
  private static $_databases = [];

  /** @var string The unique key for the current PDO instance. */
  private $uniqueKey;

  /**
   * MySQLHelper constructor.
   *
   * @param string $host The MySQL host.
   * @param string $dbname The MySQL database name.
   * @param string $username The MySQL username.
   * @param string $password The MySQL password.
   * @param bool $unique Whether to use a unique key based on caller location.
   */
  public function __construct(string $host, string $dbname, string $username, string $password, bool $unique = false)
  {
    $trace = debug_backtrace();
    $caller = $unique ? end($trace) : $trace[0];
    $callerFile = $caller['file'] ?? 'unknown';
    $callerLine = $caller['line'] ?? 'unknown';
    $this->uniqueKey = md5($host . $dbname . $username . $callerFile . $callerLine);

    if (isset(self::$_databases[$this->uniqueKey])) {
      $this->pdo = self::$_databases[$this->uniqueKey];
    } else {
      // Try connecting to the database, if it fails due to unknown database, create it
      $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
      try {
        $this->pdo = new PDO($dsn, $username, $password);
      } catch (\PDOException $e) {
        if (strpos($e->getMessage(), 'Unknown database') !== false) {
          // Connect without dbname and create the database
          $dsnNoDb = "mysql:host=$host;charset=utf8mb4";
          $pdoTmp = new PDO($dsnNoDb, $username, $password);
          $pdoTmp->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
          $pdoTmp->exec("CREATE DATABASE IF NOT EXISTS `" . str_replace('`', '', $dbname) . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
          // Now connect again with the dbname
          $this->pdo = new PDO($dsn, $username, $password);
        } else {
          throw $e;
        }
      }
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
   * @param bool $insertOrIgnore Optional. Determines whether to use INSERT IGNORE or INSERT.
   */
  public function insert(string $tableName, array $data, bool $insertOrIgnore = true): void
  {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $tableName)) {
      throw new \InvalidArgumentException('Invalid table name.');
    }

    $columns = implode(', ', array_keys($data));
    $values = implode(', ', array_fill(0, count($data), '?'));
    $sql = $insertOrIgnore ? "INSERT IGNORE" : "INSERT";
    $sql = "$sql INTO $tableName ($columns) VALUES ($values)";

    try {
      $stmt = $this->pdo->prepare($sql);
      $stmt->execute(array_values($data));
    } catch (\PDOException $e) {
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
   * Resets the AUTO_INCREMENT value of the specified table to 1.
   *
   * @param string $tableName The name of the table to reset the AUTO_INCREMENT value for.
   *
   * @throws \InvalidArgumentException If the table name is invalid.
   */
  public function resetIncrement(string $tableName): void
  {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $tableName)) {
      throw new \InvalidArgumentException('Invalid table name.');
    }
    $sql = "ALTER TABLE $tableName AUTO_INCREMENT = 1";
    $this->pdo->exec($sql);
  }
}
