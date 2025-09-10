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
class MySQLHelper extends BaseSQL
{
  /** @var PDO|null $pdo */
  public $pdo;

  /** @var PDO[] Static property to hold PDO instances. */
  private static $databases = [];

  /**
   * MySQLHelper constructor.
   *
   * @param string|PDO $hostOrPdo The MySQL host or a PDO instance.
   * @param string|null $dbname The MySQL database name (ignored if PDO is provided).
   * @param string|null $username The MySQL username (ignored if PDO is provided).
   * @param string|null $password The MySQL password (ignored if PDO is provided).
   * @param bool $unique Whether to use a unique key based on caller location.
   */
  public function __construct($hostOrPdo, $dbname = null, $username = null, $password = null, $unique = false)
  {
    $isPdo               = $hostOrPdo instanceof PDO;
    $hostOrPdoIdentifier = $isPdo ? spl_object_hash($hostOrPdo) : $hostOrPdo;
    $trace               = debug_backtrace();
    $caller              = $unique ? end($trace) : $trace[0];
    $callerFile          = isset($caller['file']) ? $caller['file'] : 'unknown';
    $callerLine          = isset($caller['line']) ? $caller['line'] : 'unknown';
    $this->uniqueKey     = md5($hostOrPdoIdentifier . ($dbname ?: '') . ($username ?: '') . $callerFile . $callerLine);

    if (isset(self::$databases[$this->uniqueKey])) {
      $this->pdo = self::$databases[$this->uniqueKey];
    } else {
      if ($isPdo) {
        $this->pdo = $hostOrPdo;
      } else {
        // Try connecting to the database, if it fails due to unknown database, create it
        $dsn = "mysql:host=$hostOrPdo;dbname=$dbname;charset=utf8mb4";
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
  public function close()
  {
    unset(self::$databases[$this->uniqueKey]);
    $this->pdo = null;
  }

  /**
   * Creates a table in the database.
   *
   * @param string $tableName The name of the table to create.
   * @param array $columns An array of column definitions.
   */
  public function createTable($tableName, $columns)
  {
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
   */
  public function insert($tableName, $data, $insertOrIgnore = true)
  {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $tableName)) {
      throw new \InvalidArgumentException('Invalid table name.');
    }

    $columns = implode(', ', array_keys($data));
    $values  = implode(', ', array_fill(0, count($data), '?'));
    $sql     = $insertOrIgnore ? 'INSERT IGNORE' : 'INSERT';
    $sql     = "$sql INTO $tableName ($columns) VALUES ($values)";

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
  public function select($tableName, $columns = '*', $where = null, $params = [])
  {
    $sql = "SELECT $columns FROM $tableName";
    if ($where) {
      $sql .= " WHERE $where";
    }
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $result ? $result : [];
  }

  /**
   * Executes a custom SQL query and returns the result.
   *
   * @param string $sql The SQL query to execute.
   * @param array $params An array of parameters to bind to the query.
   * @return array The queried result.
   */
  public function executeCustomQuery($sql, $params = [])
  {
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $result ? $result : [];
  }

  /**
   * Counts the records in the specified table.
   *
   * @param string $tableName The name of the table to count records from.
   * @param string|null $where The WHERE clause.
   * @param array $params An array of parameters to bind to the query.
   * @return int The count of records.
   */
  public function count($tableName, $where = null, $params = [])
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
  public function update($tableName, $data, $where, $params = [])
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
    $sql       = "UPDATE $tableName SET $setString WHERE $where";
    $stmt      = $this->pdo->prepare($sql);
    $stmt->execute(array_merge($setParams, $params));
  }

  /**
   * Deletes records from the specified table.
   *
   * @param string $tableName The name of the table to delete from.
   * @param string $where The WHERE clause.
   * @param array $params An array of parameters to bind to the query.
   */
  public function delete($tableName, $where, $params = [])
  {
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
  public function resetIncrement($tableName)
  {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $tableName)) {
      throw new \InvalidArgumentException('Invalid table name.');
    }
    $sql = "ALTER TABLE $tableName AUTO_INCREMENT = 1";
    $this->pdo->exec($sql);
  }

  public function getTableColumns($table)
  {
    $stmt    = $this->pdo->query("DESCRIBE `$table`");
    $columns = [];
    foreach ($stmt as $row) {
      $columns[] = $row['Field'];
    }
    return $columns;
  }

  public function addColumnIfNotExists($table, $column, $definition)
  {
    $columns = $this->getTableColumns($table);
    if (!in_array($column, $columns, true)) {
      $this->pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
  }

  public function columnExists($table, $column)
  {
    $columns = $this->getTableColumns($table);
    return in_array($column, $columns, true);
  }

  public function dropColumnIfExists($table, $column)
  {
    $columns = $this->getTableColumns($table);
    if (in_array($column, $columns, true)) {
      $this->pdo->exec("ALTER TABLE `$table` DROP COLUMN `$column`");
      return true;
    }
    return false;
  }

  public function modifyColumnIfExists($table, $column, $definition)
  {
    $columns = $this->getTableColumns($table);
    if (in_array($column, $columns, true)) {
      $this->pdo->exec("ALTER TABLE `$table` MODIFY COLUMN `$column` $definition");
      return true;
    }
    return false;
  }
}
