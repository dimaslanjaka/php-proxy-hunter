<?php

namespace PhpProxyHunter;

use PDO;

/**
 * Class MySQLHelper
 *
 * Helper class for interacting with MySQL database.
 *
 * @package PhpProxyHunter
 */
class MySQLHelper extends BaseSQL {
  /** @var PDO|null $pdo */
  public $pdo;

  /** @var PDO[] Static property to hold PDO instances. */
  private static $databases = [];

  /** @var string|null $connectionString Stored connection string for reconnection. */
  private $connectionString;

  /** @var array $connectionParams Stored connection parameters for reconnection. */
  private $connectionParams = [];

  /**
   * MySQLHelper constructor.
   *
   * @param string|PDO $hostOrPdo The MySQL host or a PDO instance.
   * @param string|null $dbname The MySQL database name (ignored if PDO is provided).
   * @param string|null $username The MySQL username (ignored if PDO is provided).
   * @param string|null $password The MySQL password (ignored if PDO is provided).
   * @param bool $unique Whether to use a unique key based on caller location.
   */
  public function __construct($hostOrPdo, $dbname = null, $username = null, $password = null, $unique = false) {
    $isPdo               = $hostOrPdo instanceof PDO;
    $hostOrPdoIdentifier = $isPdo ? spl_object_hash($hostOrPdo) : $hostOrPdo;
    $trace               = debug_backtrace();
    $caller              = $unique ? end($trace) : $trace[0];
    $callerFile          = isset($caller['file']) ? $caller['file'] : 'unknown';
    $callerLine          = isset($caller['line']) ? $caller['line'] : 'unknown';
    $this->uniqueKey     = md5($hostOrPdoIdentifier . ($dbname ?: '') . ($username ?: '') . $callerFile . $callerLine);

    // Store connection parameters for potential reconnection
    if (!$isPdo) {
      $this->connectionParams = [
        'host'     => $hostOrPdo,
        'dbname'   => $dbname,
        'username' => $username,
        'password' => $password,
      ];
    }

    if (isset(self::$databases[$this->uniqueKey])) {
      $this->pdo = self::$databases[$this->uniqueKey];
    } else {
      if ($isPdo) {
        $this->pdo = $hostOrPdo;
      } else {
        // Try connecting to the database, if it fails due to unknown database, create it
        $dsn                    = "mysql:host=$hostOrPdo;dbname=$dbname;charset=utf8mb4";
        $this->connectionString = $dsn;
        try {
          $this->pdo = new PDO($dsn, $username, $password);
        } catch (\PDOException $e) {
          $msg = $e->getMessage();
          if (strpos($msg, 'Unknown database') !== false) {
            // Connect without dbname and create the database
            $dsnNoDb = "mysql:host=$hostOrPdo;charset=utf8mb4";
            $pdoTmp  = new PDO($dsnNoDb, $username, $password);
            $pdoTmp->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdoTmp->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', $dbname) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            // Now connect again with the dbname
            $this->pdo = new PDO($dsn, $username, $password);
          } elseif (strpos($msg, "Plugin 'mysql_native_password' is not loaded") !== false) {
            // Try to auto-install the plugin (requires SUPER privilege)
            $dll = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'mysql_native_password.dll' : 'mysql_native_password.so';
            try {
              $dsnNoDb = "mysql:host=$hostOrPdo;charset=utf8mb4";
              $pdoTmp  = new PDO($dsnNoDb, $username, $password);
              $pdoTmp->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
              $pdoTmp->exec("INSTALL PLUGIN mysql_native_password SONAME '" . $dll . "';");
              // Try again to connect
              $this->pdo = new PDO($dsn, $username, $password);
            } catch (\PDOException $e2) {
              // Prepare the SQL for error message
              $alterUserSql = "ALTER USER '" . addslashes($username) . "'@'" . addslashes($hostOrPdo ?: '%') . "' IDENTIFIED WITH caching_sha2_password BY '" . addslashes($password) . "';";
              // Attempt to change the user's plugin to caching_sha2_password
              try {
                $dsnNoDb2 = "mysql:host=$hostOrPdo;charset=utf8mb4";
                $pdoTmp2  = new PDO($dsnNoDb2, $username, $password);
                $pdoTmp2->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdoTmp2->exec($alterUserSql);
                // Try again to connect
                $this->pdo = new PDO($dsn, $username, $password);
                $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$databases[$this->uniqueKey] = $this->pdo;
                return;
              } catch (\PDOException $e3) {
                throw new \RuntimeException(
                  "[MySQLHelper] Unable to connect: both attempts to auto-install 'mysql_native_password' and switch user to 'caching_sha2_password' failed.\n" .
                    "\nManual intervention required. Please execute one of the following as an admin in MySQL:\n" .
                    "  1. INSTALL PLUGIN mysql_native_password SONAME '" . $dll . "';\n" .
                    '  2. ' . $alterUserSql . "\n" .
                    "\n---\n" .
                    'Auto-install error:   ' . $e2->getMessage() . "\n" .
                    'Switch plugin error:  ' . $e3->getMessage() . "\n"
                );
              }
            }
          } else {
            throw $e;
          }
        }
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
   * Check if the connection is still alive.
   * @return bool True if connection is valid, false otherwise.
   */
  public function isConnectionAlive() {
    try {
      if (!$this->pdo) {
        return false;
      }
      // Try a simple query to verify connection
      $this->pdo->query('SELECT 1');
      return true;
    } catch (\PDOException $e) {
      return false;
    }
  }

  /**
   * Check if a PDOException is a connection loss error and attempt to reconnect.
   * @param \PDOException $e The exception to check.
   * @return bool True if reconnection was successful, false otherwise.
   */
  private function isConnectionLossError(\PDOException $e) {
    $msg = $e->getMessage();
    // Check for common connection loss error codes/messages
    $connectionLossPatterns = [
      'disconnected by the server',  // Error 4031
      'MySQL server has gone away',
      'no connection to the server',
      'lost connection',
      'connection refused',
      'Can\'t connect to MySQL server',
    ];

    foreach ($connectionLossPatterns as $pattern) {
      if (stripos($msg, $pattern) !== false) {
        return true;
      }
    }

    // Check SQLSTATE code
    if (isset($e->errorInfo[0])) {
      // HY000 (General error) is often used for connection loss
      if ($e->errorInfo[0] === 'HY000' || $e->errorInfo[0] === '08006' || $e->errorInfo[0] === '08003') {
        return true;
      }
    }

    return false;
  }

  /**
   * Attempt to reconnect to the database.
   * @return bool True if reconnection was successful, false otherwise.
   */
  private function reconnect() {
    if (empty($this->connectionParams)) {
      return false;
    }

    try {
      $host     = $this->connectionParams['host'];
      $dbname   = $this->connectionParams['dbname'];
      $username = $this->connectionParams['username'];
      $password = $this->connectionParams['password'];
      $dsn      = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";

      $this->pdo = new PDO($dsn, $username, $password);
      $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

      // Update the cached database instance
      self::$databases[$this->uniqueKey] = $this->pdo;

      return true;
    } catch (\PDOException $e) {
      return false;
    }
  }

  /**
   * Wrap a PDO operation with connection loss handling and automatic reconnection.
   * @param callable $operation The operation to perform.
   * @param string $operationName Name of the operation for logging.
   * @return mixed The result of the operation.
   */
  private function withConnectionRetry(callable $operation, $operationName = 'database operation') {
    try {
      return call_user_func($operation);
    } catch (\PDOException $e) {
      if ($this->isConnectionLossError($e)) {
        // Try to reconnect
        if ($this->reconnect()) {
          // Retry the operation once after reconnection
          try {
            return call_user_func($operation);
          } catch (\PDOException $retryException) {
            throw $retryException;
          }
        }
      }
      throw $e;
    }
  }

  /**
   * Creates a table in the database.
   *
   * @param string $tableName The name of the table to create.
   * @param array $columns An array of column definitions.
   */
  public function createTable($tableName, $columns) {
    $columnsString = implode(', ', $columns);
    $sql           = "CREATE TABLE IF NOT EXISTS $tableName ($columnsString)";
    $this->pdo->exec($sql);
  }

  /**
   * Inserts a record into the specified table.
   *
   * @param string $tableName The name of the table to insert into.
   * @param array $data An associative array of column names and values.
   * @param bool $insertOrIgnore Determines whether to use INSERT IGNORE or INSERT.
   * @return bool True on success, false on failure.
   */
  public function insert($tableName, $data, $insertOrIgnore = true): bool {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $tableName)) {
      throw new \InvalidArgumentException('Invalid table name.');
    }

    if (empty($data) || !is_array($data)) {
      return false;
    }

    $columns = implode(', ', array_keys($data));
    $values  = implode(', ', array_fill(0, count($data), '?'));
    $sql     = $insertOrIgnore ? 'INSERT IGNORE' : 'INSERT';
    $sql     = "$sql INTO $tableName ($columns) VALUES ($values)";

    $operation = function () use ($sql, $data) {
      $stmt = $this->pdo->prepare($sql);
      $stmt->execute(array_values($data));
      return true;
    };

    try {
      return $this->withConnectionRetry($operation, 'insert');
    } catch (\PDOException $e) {
      // Do not throw; return false to indicate failure
      return false;
    }
  }

  /**
   * Selects records from the specified table.
   *
   * @param string            $tableName The name of the table to select from.
  * @param string            $columns The columns to select (string).
   * @param string|null       $where The WHERE clause.
   * @param array             $params An array of parameters to bind to the query.
   * @param string|null       $orderBy The ORDER BY clause (without "ORDER BY" keyword).
   * @param int|null          $limit The LIMIT value.
   * @param int|null          $offset The OFFSET value.
   * @return array An array containing the selected records.
   */
  public function select($tableName, $columns = '*', $where = null, $params = [], $orderBy = null, $limit = null, $offset = null) {
    $operation = function () use ($tableName, $columns, $where, $params, $orderBy, $limit, $offset) {
      // Allow passing columns as an array of column names
      if (is_array($columns)) {
        $quote = function ($identifier) {
          return '`' . str_replace('`', '``', $identifier) . '`';
        };
        $columns = implode(', ', array_map($quote, $columns));
      }

      $sql = "SELECT $columns FROM $tableName";
      if ($where) {
        $sql .= " WHERE $where";
      }
      if ($orderBy) {
        $sql .= " ORDER BY $orderBy";
      }
      if ($limit !== null) {
        if ($offset !== null) {
          // MySQL supports LIMIT offset, count
          $sql .= " LIMIT $offset, $limit";
        } else {
          $sql .= " LIMIT $limit";
        }
      } elseif ($offset !== null) {
        // If only offset provided, use large limit to allow offset
        $sql .= " LIMIT 18446744073709551615 OFFSET $offset";
        // MySQL max
      }
      $stmt = $this->pdo->prepare($sql);
      // Normalize params: strip leading ':' from named keys for execute()
      if (is_array($params)) {
        $normalized = [];
        foreach ($params as $k => $v) {
          if (is_string($k) && strlen($k) > 0 && $k[0] === ':') {
            $normalized[ltrim($k, ':')] = $v;
          } else {
            $normalized[$k] = $v;
          }
        }
        $params = $normalized;
      }
      $stmt->execute($params);
      $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
      return $result ? $result : [];
    };

    return $this->withConnectionRetry($operation, 'select');
  }

  /**
   * Executes a custom SQL query and returns the result.
   *
   * @param string $sql The SQL query to execute.
   * @param array $params An array of parameters to bind to the query.
   * @return array The queried result.
   */
  public function execute($sql, $params = []) {
    $operation = function () use ($sql, $params) {
      $stmt = $this->pdo->prepare($sql);
      if (is_array($params)) {
        $normalized = [];
        foreach ($params as $k => $v) {
          if (is_string($k) && strlen($k) > 0 && $k[0] === ':') {
            $normalized[ltrim($k, ':')] = $v;
          } else {
            $normalized[$k] = $v;
          }
        }
        $params = $normalized;
      }
      $stmt->execute($params);
      $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
      return $result ? $result : [];
    };

    return $this->withConnectionRetry($operation, 'execute');
  }

  /**
   * Counts the records in the specified table.
   *
   * @param string $tableName The name of the table to count records from.
   * @param string|null $where The WHERE clause.
   * @param array $params An array of parameters to bind to the query.
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
   * @param array $data An associative array of column names and values to update.
   * @param string $where The WHERE clause.
   * @param array $params An array of parameters to bind to the query.
   */
  public function update($tableName, $data, $where, $params = []) {
    $operation = function () use ($tableName, $data, $where, $params) {
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
    };

    $this->withConnectionRetry($operation, 'update');
  }

  /**
   * Deletes records from the specified table.
   *
   * @param string $tableName The name of the table to delete from.
   * @param string $where The WHERE clause.
   * @param array $params An array of parameters to bind to the query.
   */
  public function delete($tableName, $where, $params = []) {
    $sql  = "DELETE FROM $tableName WHERE $where";
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
  public function resetIncrement($tableName) {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $tableName)) {
      throw new \InvalidArgumentException('Invalid table name.');
    }
    $sql = "ALTER TABLE $tableName AUTO_INCREMENT = 1";
    $this->pdo->exec($sql);
  }

  public function getTableColumns($table) {
    $stmt    = $this->pdo->query("DESCRIBE `$table`");
    $columns = [];
    foreach ($stmt as $row) {
      $columns[] = $row['Field'];
    }
    return $columns;
  }

  /**
   * Calculate a deterministic checksum for a table (MySQL).
   *
   * Tries `CHECKSUM TABLE` first; if unavailable falls back to computing
   * per-row SHA2 hashes, concatenating them ordered, then SHA2 the result.
   *
   * @param string $table
   * @param array|null $columns
   * @return string|null
   */
  public function calculateChecksum($table, $columns = null) {
    if ($columns === null) {
      $columns = $this->getTableColumns($table);
    }

    if (empty($columns)) {
      return null;
    }

    // Quote columns and table
    $quote = function ($identifier) {
      return '`' . str_replace('`', '``', $identifier) . '`';
    };
    $escapedCols = array_map($quote, $columns);
    $concatExpr  = 'CONCAT_WS(\'|\',' . implode(',', $escapedCols) . ')';

    // Try CHECKSUM TABLE
    try {
      $result = $this->execute('CHECKSUM TABLE `' . str_replace('`', '``', $table) . '`');
      if (!empty($result) && isset($result[0]['Checksum'])) {
        return (string)$result[0]['Checksum'];
      }
    } catch (\Throwable $e) {
      // ignore and fallback
    }

    // Fallback: SHA2 of grouped per-row hashes
    $orderCol = $escapedCols[0];
    $sql      = "SELECT SHA2(GROUP_CONCAT(row_hash ORDER BY row_hash SEPARATOR '\n'), 256) AS checksum FROM (SELECT SHA2($concatExpr, 256) AS row_hash FROM `" . str_replace('`', '``', $table) . '` ORDER BY ' . $orderCol . ') AS t';

    $result = $this->execute($sql);
    return $result[0]['checksum'] ?? null;
  }

  public function addColumnIfNotExists($table, $column, $definition) {
    $columns = $this->getTableColumns($table);
    if (!in_array($column, $columns, true)) {
      $this->pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
  }

  public function columnExists($table, $column) {
    $columns = $this->getTableColumns($table);
    return in_array($column, $columns, true);
  }

  public function dropColumnIfExists($table, $column) {
    $columns = $this->getTableColumns($table);
    if (in_array($column, $columns, true)) {
      $this->pdo->exec("ALTER TABLE `$table` DROP COLUMN `$column`");
      return true;
    }
    return false;
  }

  public function modifyColumnIfExists($table, $column, $definition) {
    $columns = $this->getTableColumns($table);
    if (in_array($column, $columns, true)) {
      $this->pdo->exec("ALTER TABLE `$table` MODIFY COLUMN `$column` $definition");
      return true;
    }
    return false;
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

  public function hasTable($table) {
    $stmt = $this->pdo->prepare('SHOW TABLES LIKE ?');
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
  }
}
