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
abstract class BaseSQL
{
  /** @var PDO|null Database connection instance */
  protected $pdo;

  /** @var string The unique key for the current PDO instance. */
  protected $uniqueKey;

  /**
   * Close the database connection.
   *
   * @return void
   */
  public function close()
  {
    $this->pdo = null;
  }

  /**
   * Create a table in the database.
   *
   * @param string $tableName The name of the table to create.
   * @param array  $columns   Column definitions.
   * @return void
   */
  abstract public function createTable($tableName, $columns);

  /**
   * Insert a record into a table.
   *
   * @param string $tableName The target table.
   * @param array  $data      Associative array of column => value.
   * @param bool   $insertOrIgnore Whether to use "INSERT OR IGNORE".
   * @return void
   */
  abstract public function insert($tableName, $data, $insertOrIgnore = true);

  /**
   * Select records from a table.
   *
   * @param string      $tableName The target table.
   * @param string      $columns   The columns to select.
   * @param string|null $where     The WHERE clause.
   * @param array       $params    Parameters for the query.
   * @return array
   */
  abstract public function select($tableName, $columns = '*', $where = null, $params = []);

  /**
   * Execute a custom SQL query.
   *
   * @param string $sql    SQL query.
   * @param array  $params Parameters for the query.
   * @return array
   */
  abstract public function executeCustomQuery($sql, $params = []);

  /**
   * Count records in a table.
   *
   * @param string      $tableName The target table.
   * @param string|null $where     The WHERE clause.
   * @param array       $params    Parameters for the query.
   * @return int
   */
  abstract public function count($tableName, $where = null, $params = []);

  /**
   * Update records in a table.
   *
   * @param string $tableName The target table.
   * @param array  $data      Data to update.
   * @param string $where     The WHERE clause.
   * @param array  $params    Parameters for the query.
   * @return void
   */
  abstract public function update($tableName, $data, $where, $params = []);

  /**
   * Delete records from a table.
   *
   * @param string $tableName The target table.
   * @param string $where     The WHERE clause.
   * @param array  $params    Parameters for the query.
   * @return void
   */
  abstract public function delete($tableName, $where, $params = []);
}
