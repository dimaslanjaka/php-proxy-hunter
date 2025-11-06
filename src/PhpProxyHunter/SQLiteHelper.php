<?php

namespace PhpProxyHunter;

use PDO;

/**
 * Class SQLiteHelper
 *
 * Helper class for interacting with SQLite database.
 *
 * @package PhpProxyHunter
 */

class SQLiteHelper extends BaseSQL {
  /** @var PDO|null $pdo */
  public $pdo;

  /** @var PDO[] Static property to hold PDO instances. */
  private static $databases = [];

  /**
   * @var string Unique key for the PDO instance.
   */
  protected $uniqueKey;


  /**
   * SQLiteHelper constructor.
   *
   * @param string|PDO $dbPathOrPdo The path to the SQLite database file or a PDO instance.
   * @param bool $unique Whether to use a unique key based on caller location.
   */
  public function __construct($dbPathOrPdo, $unique = false) {
    $trace = debug_backtrace();
    // Unique key is based on the last caller if $unique is true
    $dbPathOrPdoIdentifier = is_string($dbPathOrPdo) ? $dbPathOrPdo : spl_object_hash($dbPathOrPdo);
    $caller                = $unique ? end($trace) : $trace[0];
    $callerFile            = isset($caller['file']) ? $caller['file'] : 'unknown';
    $callerLine            = isset($caller['line']) ? $caller['line'] : 'unknown';
    $this->uniqueKey       = md5($dbPathOrPdoIdentifier . $callerFile . $callerLine);

    // Avoid multiple PDO instance
    if (isset(self::$databases[$this->uniqueKey])) {
      $this->pdo = self::$databases[$this->uniqueKey];
    } else {
      if ($dbPathOrPdo instanceof PDO) {
        $this->pdo = $dbPathOrPdo;
      } else {
        $this->pdo = new PDO('sqlite:' . $dbPathOrPdo); // ;busyTimeout=10000
        // Set how long (in seconds) SQLite will wait if the database is locked
        $this->pdo->setAttribute(PDO::ATTR_TIMEOUT, 10);
        // Enable exceptions for error handling
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      }
      self::$databases[$this->uniqueKey] = $this->pdo;
    }
  }

  /**
   * Closes the database connection.
   */
  public function close() {
    unset(self::$databases[$this->uniqueKey]);
    $this->pdo = null;
  }

  /**
   * Creates a table in the database.
   *
   * @param string $tableName The name of the table to create.
   * @param array  $columns   An array of column definitions.
   */
  public function createTable($tableName, $columns) {
    // Remove empty or whitespace-only columns to avoid syntax errors
    $columns       = array_filter(array_map('trim', $columns));
    $columnsString = implode(', ', $columns);
    $sql           = "CREATE TABLE IF NOT EXISTS $tableName ($columnsString)";
    $this->pdo->exec($sql);
  }

  /**
   * Inserts a record into the specified table.
   *
   * @param string $tableName The name of the table to insert into.
   * @param array  $data      An associative array of column names and values.
   * @param bool   $insertOrIgnore Optional. Determines whether to use INSERT OR IGNORE or INSERT.
   * @return bool True on success, false on failure.
   */
  public function insert($tableName, $data, $insertOrIgnore = true): bool {
    // Ensure the table name is valid (alphanumeric and underscores only)
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $tableName)) {
      throw new \InvalidArgumentException('Invalid table name.');
    }

    if (empty($data) || !is_array($data)) {
      return false;
    }

    $columns = implode(', ', array_keys($data));
    $values  = implode(', ', array_fill(0, count($data), '?'));
    $sql     = $insertOrIgnore ? 'INSERT OR IGNORE' : 'INSERT';
    $sql     = "$sql INTO $tableName ($columns) VALUES ($values)";

    try {
      $stmt = $this->pdo->prepare($sql);
      $stmt->execute(array_values($data));
      return true;
    } catch (\PDOException $e) {
      // Fail silently and return false to indicate failure
      return false;
    }
  }

  /**
   * Selects records from the specified table.
   *
   * @param string            $tableName The name of the table to select from.
  * @param string            $columns   The columns to select (string).
   * @param string|null       $where     The WHERE clause.
   * @param array             $params    An array of parameters to bind to the query.
   * @param string|null       $orderBy   The ORDER BY clause (without "ORDER BY" keyword).
   * @param int|null          $limit     The LIMIT value.
   * @param int|null          $offset    The OFFSET value.
   * @return array An array containing the selected records.
   */
  public function select($tableName, $columns = '*', $where = null, $params = [], $orderBy = null, $limit = null, $offset = null) {
    $sql = "SELECT $columns FROM $tableName";
    if ($where) {
      $sql .= " WHERE $where";
    }
    if ($orderBy) {
      $sql .= " ORDER BY $orderBy";
    }
    if ($limit !== null) {
      $sql .= " LIMIT $limit";
      if ($offset !== null) {
        $sql .= " OFFSET $offset";
      }
    } elseif ($offset !== null) {
      // If offset is provided without limit, still apply LIMIT -1 for SQLite to allow offset
      $sql .= " LIMIT -1 OFFSET $offset";
    }
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $result ? $result : [];
  }

  /**
   * Executes a custom SQL query and returns the result.
   *
   * @param string $sql    The SQL query to execute.
   * @param array  $params An array of parameters to bind to the query.
   * @return array The queried result.
   */
  public function execute($sql, $params = []) {
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $result ? $result : [];
  }

  /**
   * Counts the records in the specified table.
   *
   * @param string      $tableName The name of the table to count records from.
   * @param string|null $where     The WHERE clause.
   * @param array       $params    An array of parameters to bind to the query.
   * @return int The count of records.
   */
  public function count($tableName, $where = null, $params = []) {
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
   * @param array  $data      An associative array of column names and values to update.
   * @param string $where     The WHERE clause.
   * @param array  $params    An array of parameters to bind to the query.
   */
  public function update($tableName, $data, $where, $params = []) {
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
    $sql       = "UPDATE $tableName SET $setString WHERE $where";
    $stmt      = $this->pdo->prepare($sql);
    $stmt->execute(array_merge($setParams, $params));
  }

  /**
   * Deletes records from the specified table.
   *
   * @param string $tableName The name of the table to delete from.
   * @param string $where     The WHERE clause.
   * @param array  $params    An array of parameters to bind to the query.
   */
  public function delete($tableName, $where, $params = []) {
    $sql  = "DELETE FROM $tableName WHERE $where";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
  }

  /**
   * Checks if the database is currently locked.
   *
   * @return bool True if the database is locked, false otherwise.
   */
  public function isDatabaseLocked() {
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

  public function getTableColumns($table) {
    $stmt    = $this->pdo->query("PRAGMA table_info($table)");
    $columns = [];
    foreach ($stmt as $row) {
      $columns[] = $row['name'];
    }
    return $columns;
  }

  public function addColumnIfNotExists($table, $column, $definition) {
    $columns = $this->getTableColumns($table);
    if (!in_array($column, $columns, true)) {
      $this->pdo->exec("ALTER TABLE $table ADD COLUMN $column $definition");
      return true;
    }
    return false;
  }

  public function columnExists($table, $column) {
    $columns = $this->getTableColumns($table);
    return in_array($column, $columns, true);
  }

  public function dropColumnIfExists($table, $column) {
    $columns = $this->getTableColumns($table);
    if (!in_array($column, $columns, true)) {
      return false;
    }
    // Remove the column from the list
    $newColumns = array_filter($columns, function ($col) use ($column) {
      return $col !== $column;
    });
    // Set busy timeout to help with locks
    $this->pdo->exec('PRAGMA busy_timeout = 5000');
    // Wrap schema change in a transaction
    try {
      $this->pdo->beginTransaction();
      // Get table schema
      $stmt = $this->pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='$table'");
      $row  = $stmt->fetch(PDO::FETCH_ASSOC);
      if (!$row) {
        throw new \RuntimeException("Table $table does not exist");
      }
      $createSql = $row['sql'];
      // Release the statement to avoid locking the table
      $stmt = null;
      // Build new CREATE TABLE statement without the column
      $pattern = '/\((.*)\)/s';
      if (!preg_match($pattern, $createSql, $matches)) {
        throw new \RuntimeException("Could not parse CREATE TABLE statement for $table");
      }
      $colsDef = $matches[1];
      $colsArr = array_filter(array_map('trim', explode(',', $colsDef)), function ($def) use ($column) {
        return stripos($def, $column) === false;
      });
      $newCreateSql = preg_replace($pattern, '(' . implode(', ', $colsArr) . ')', $createSql);
      $tmpTable     = $table . '_TEMP';
      $newCreateSql = str_replace($table, $tmpTable, $newCreateSql);
      // var_dump($newCreateSql);
      try {
        $this->pdo->exec($newCreateSql);
      } catch (\PDOException $ex) {
        // Debug: output the generated SQL to help diagnose syntax errors
        $debugMsg = "\n---\nSQLITE CREATE TABLE DEBUG\n---\n" . $newCreateSql . "\n---\n" . $ex->getMessage() . "\n---\n";
        @file_put_contents(__DIR__ . '/sqlite_create_table_debug.sql', $debugMsg);
        error_log($debugMsg);
        throw $ex;
      }
      $colsList  = implode(', ', $newColumns);
      $insertSql = "INSERT INTO $tmpTable ($colsList) SELECT $colsList FROM $table";
      // var_dump($insertSql);
      $this->pdo->exec($insertSql);
      $this->pdo->exec("DROP TABLE $table");
      $this->pdo->exec("ALTER TABLE $tmpTable RENAME TO $table");
      $this->pdo->commit();
      return true;
    } catch (\Exception $e) {
      if ($this->pdo->inTransaction()) {
        $this->pdo->rollBack();
      }
      throw $e;
    }
  }

  public function modifyColumnIfExists($table, $column, $definition) {
    $columns = $this->getTableColumns($table);
    if (!in_array($column, $columns, true)) {
      return false;
    }
    try {
      $this->pdo->beginTransaction();
      // Get table schema
      $stmt = $this->pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='$table'");
      $row  = $stmt->fetch(PDO::FETCH_ASSOC);
      if (!$row) {
        throw new \RuntimeException("Table $table does not exist");
      }
      $createSql = $this->fixSQLiteSyntax($row['sql']);
      $stmt      = null;
      // Parse columns definition
      $pattern = '/\((.*)\)/s';
      if (!preg_match($pattern, $createSql, $matches)) {
        throw new \RuntimeException("Could not parse CREATE TABLE statement for $table");
      }
      $colsDef    = $matches[1];
      $colsArr    = array_map('trim', explode(',', $colsDef));
      $newColsArr = [];
      foreach ($colsArr as $colDef) {
        // Replace only the target column definition
        if (preg_match('/^"?' . preg_quote($column, '/') . '"?\s+/i', $colDef)) {
          $newColsArr[] = '"' . $column . '" ' . $definition;
        } else {
          $newColsArr[] = $colDef;
        }
      }
      $newCreateSql = preg_replace($pattern, '(' . implode(', ', $newColsArr) . ')', $createSql);
      $tmpTable     = $table . '_TEMP';
      $newCreateSql = str_replace($table, $tmpTable, $newCreateSql);
      $this->pdo->exec($newCreateSql);
      $colsList  = implode(', ', $columns);
      $insertSql = "INSERT INTO $tmpTable ($colsList) SELECT $colsList FROM $table";
      // var_dump('[modifyColumn] create sql', $createSql);
      // var_dump('[modifyColumn] new create sql', $newCreateSql);
      // var_dump('[modifyColumn] insert sql', $insertSql);
      $this->pdo->exec($insertSql);
      $this->pdo->exec("DROP TABLE $table");
      $this->pdo->exec("ALTER TABLE $tmpTable RENAME TO $table");
      $this->pdo->commit();
      return true;
    } catch (\Exception $e) {
      if ($this->pdo->inTransaction()) {
        $this->pdo->rollBack();
      }
      throw $e;
    }
  }

  public function getTableSchema($table) {
    $stmt = $this->pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='$table'");
    $row  = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $row['sql'] : null;
  }

  public function beginTransaction() {
    return $this->pdo->beginTransaction();
  }

  public function commit() {
    return $this->pdo->commit();
  }

  public function rollback() {
    return $this->pdo->rollBack();
  }

  public function fixSQLiteSyntax($sql) {
    // Remove Comments
    $sql = preg_replace('/--.*$/m', '', $sql);
    // Remove multiple spaces
    $sql = preg_replace('/\s+/', ' ', $sql);
    return trim($sql);
  }

  public function hasTable($table) {
    $stmt = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=?");
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
  }
}
