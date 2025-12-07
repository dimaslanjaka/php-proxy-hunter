<?php

namespace PhpProxyHunter;

/**
 * Abstract class BaseSQL
 *
 * Provides a common interface for SQL database helpers.
 * Specific implementations (SQLite, MySQL, etc.) must extend this class.
 *
 * @package PhpProxyHunter
 */
abstract class BaseSQL {
  /** @var \PDO|null Database connection instance */
  protected $pdo;

  /** @var string The unique key for the current PDO instance. */
  protected $uniqueKey;

  /**
   * Close the database connection.
   *
   * @return void
   */
  abstract public function close();

  /**
   * Create a table in the database.
   *
   * @param string $tableName The name of the table to create.
   * @param array $columns Column definitions.
   * @return void
   */
  abstract public function createTable($tableName, array $columns);

  /**
   * Insert a record into a table.
   *
   * @param string $tableName The target table.
   * @param array $data Associative array of column => value.
   * @param bool $insertOrIgnore Whether to use "INSERT OR IGNORE".
   * @return bool True on success, false on failure.
   */
  abstract public function insert($tableName, array $data, $insertOrIgnore = true);

  /**
   * Select records from a table.
   *
   * @param string $tableName The target table.
  * @param string $columns The columns to select (string).
   * @param string|null $where The WHERE clause.
   * @param array $params Parameters for the query.
   * @param string|null $orderBy The ORDER BY clause (without "ORDER BY" keyword).
   * @param int|null $limit The LIMIT value (number of rows to return).
   * @param int|null $offset The OFFSET value (number of rows to skip).
   * @return array
   */
  abstract public function select($tableName, $columns = '*', $where = null, array $params = [], $orderBy = null, $limit = null, $offset = null);

  /**
   * Execute a custom SQL query.
   *
   * @param string $sql SQL query.
   * @param array $params Parameters for the query.
   * @return array
   */
  abstract public function execute($sql, array $params = []);

  /**
   * Count records in a table.
   *
   * @param string $tableName The target table.
   * @param string|null $where The WHERE clause.
   * @param array $params Parameters for the query.
   * @return int
   */
  abstract public function count($tableName, $where = null, array $params = []);

  /**
   * Update records in a table.
   *
   * @param string $tableName The target table.
   * @param array $data Data to update.
   * @param string $where The WHERE clause.
   * @param array $params Parameters for the query.
   * @return void
   */
  abstract public function update($tableName, array $data, $where, array $params = []);

  /**
   * Delete records from a table.
   *
   * @param string $tableName The target table.
   * @param string $where The WHERE clause.
   * @param array $params Parameters for the query.
   * @return void
   */
  abstract public function delete($tableName, $where, array $params = []);

  /**
   * Add a column to a table if it does not exist.
   *
   * @param string $table The table name.
   * @param string $column The column name.
   * @param string $definition The column definition (SQL fragment).
   * @return bool True if the column was added, false if it already existed or failed.
   */
  abstract public function addColumnIfNotExists($table, $column, $definition);

  /**
   * Check if a column exists in a table.
   *
   * @param string $table The table name.
   * @param string $column The column name.
   * @return bool True if the column exists, false otherwise.
   */
  abstract public function columnExists($table, $column);

  /**
   * Drop a column from a table if it exists.
   *
   * @param string $table The table name.
   * @param string $column The column name.
   * @return bool True if the column was dropped, false if it did not exist or failed.
   */
  abstract public function dropColumnIfExists($table, $column);

  /**
   * Get all columns of a table.
   *
   * @param string $table The table name.
   * @return array List of column names.
   */
  abstract public function getTableColumns($table);

  /**
   * Modify a column in a table if it exists.
   *
   * @param string $table The table name.
   * @param string $column The column name.
   * @param string $definition The new column definition (SQL fragment).
   * @return bool True if the column was modified, false if it did not exist or failed.
   */
  abstract public function modifyColumnIfExists($table, $column, $definition);

  /**
   * Begin a database transaction.
   *
   * @return bool
   */
  abstract public function beginTransaction();

  /**
   * Commit the current transaction.
   *
   * @return bool
   */
  abstract public function commit();

  /**
   * Roll back the current transaction.
   *
   * @return bool
   */
  abstract public function rollback();

  /**
   * Check if a table exists in the database.
   *
   * @param string $table The table name.
   * @return bool True if the table exists, false otherwise.
   */
  abstract public function hasTable($table);

  /**
   * Calculate a deterministic checksum for the table.
   *
   * The actual implementation differs per driver:
   * - MySQL  : may use CHECKSUM TABLE or SHA/GROUP_CONCAT hashing
   * - SQLite : must hash sorted rows manually
   *
   * @param string $table The table name.
   * @param array|null $columns Optional list of columns to include. If null, include all columns.
   * @return string|null Returns the checksum string, or null on failure.
   */
  abstract public function calculateChecksum($table, array $columns = null);
}
