<?php

namespace PhpProxyHunter;

use \PDO;

if (!defined('PHP_PROXY_HUNTER')) exit('access denied');

/**
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
 */
class SQLiteHelper
{
  private $pdo;

  public function __construct($dbPath)
  {
    $this->pdo = new PDO("sqlite:$dbPath");
    $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  }

  public function createTable($tableName, $columns)
  {
    $columnsString = implode(', ', $columns);
    $sql = "CREATE TABLE IF NOT EXISTS $tableName ($columnsString)";
    $this->pdo->exec($sql);
  }

  public function insert($tableName, $data)
  {
    $columns = implode(', ', array_keys($data));
    $values = implode(', ', array_fill(0, count($data), '?'));

    $sql = "INSERT INTO $tableName ($columns) VALUES ($values)";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute(array_values($data));
  }

  /**
   * select from table
   *
   * ```php
   * // sample usage
   * $helper->select('users', '*', 'name = ?', ['John']);
   * ```
   */
  public function select(string $tableName, string $columns = '*', string $where = null, array $params = [])
  {
    $sql = "SELECT $columns FROM $tableName";
    if ($where) {
      $sql .= " WHERE $where";
    }
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  public function update($tableName, array $data, string $where, $params = [])
  {
    $setValues = [];
    foreach ($data as $key => $value) {
      $setValues[] = "$key = ?";
    }
    $setString = implode(', ', $setValues);
    $sql = "UPDATE $tableName SET $setString WHERE $where";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute(array_merge(array_values($data), $params));
  }

  public function delete($tableName, $where, $params = [])
  {
    $sql = "DELETE FROM $tableName WHERE $where";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
  }
}
