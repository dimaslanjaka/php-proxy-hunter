<?php

namespace PhpProxyHunter;

use \PDO;

if (!defined('PHP_PROXY_HUNTER')) exit('access denied');

/**
 * Class SQLiteHelper
 *
 * Helper class for interacting with SQLite database.
 *
 * Usage Example:
 *
 * // Create an instance of the SQLiteHelper class
 * $helper = new SQLiteHelper('path/to/your/database.sqlite');
 *
 * // Create a table
 * $helper->createTable('users', [
 *     'id INTEGER PRIMARY KEY',
 *     'name TEXT',
 *     'email TEXT'
 * ]);
 *
 * // Insert a record
 * $helper->insert('users', ['name' => 'John', 'email' => 'john@example.com']);
 *
 * // Select records
 * $users = $helper->select('users', '*', 'name = ?', ['John']);
 * print_r($users);
 *
 * // Update a record
 * $helper->update('users', ['email' => 'john.doe@example.com'], 'name = ?', ['John']);
 *
 * // Delete a record
 * $helper->delete('users', 'name = ?', ['John']);
 *
 * @package PhpProxyHunter
 */
class SQLiteHelper
{
  /** @var PDO $pdo */
  private $pdo;

  /**
   * SQLiteHelper constructor.
   *
   * @param string $dbPath The path to the SQLite database file.
   */
  public function __construct(string $dbPath)
  {
    $this->pdo = new PDO("sqlite:$dbPath");
    $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
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
   */
  public function insert(string $tableName, array $data): void
  {
    $columns = implode(', ', array_keys($data));
    $values = implode(', ', array_fill(0, count($data), '?'));

    $sql = "INSERT INTO $tableName ($columns) VALUES ($values)";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute(array_values($data));
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
    if ($result) return $result;
    return [];
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
      if (empty($value)) {
        $setValues[] = "$key = NULL";
      } else {
        $setValues[] = "$key = ?";
        $setParams[] = $value;
      }
    }
    $setString = implode(', ', $setValues);
    $sql = "UPDATE $tableName SET $setString WHERE $where";
//    echo $sql . PHP_EOL;
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
}
