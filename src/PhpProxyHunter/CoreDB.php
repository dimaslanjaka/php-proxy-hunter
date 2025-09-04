<?php

namespace PhpProxyHunter;

class CoreDB
{
  /**
   * @var SQLiteHelper|MySQLHelper $db Database helper instance (SQLite or MySQL)
   */
  public $db;

  /**
   * @var string|null $driver Database driver type ('mysql' or 'sqlite')
   */
  public $driver = null;

  /**
   * CoreDB constructor.
   *
   * Initializes the database connection using MySQL or SQLite.
   * Attempts to connect to MySQL first; falls back to SQLite if MySQL connection fails.
   *
   * @param string|null $dbLocation Path to SQLite database file (if using SQLite).
   * @param string $host MySQL host.
   * @param string $dbname MySQL database name.
   * @param string $username MySQL username.
   * @param string $password MySQL password.
   * @param bool $unique Whether to use a unique MySQL connection (custom flag).
   * @param string|null $type Database type ('mysql' or 'sqlite'). If null, tries MySQL first then SQLite.
   */
  public function __construct($dbLocation = null, $host = 'localhost', $dbname = 'php_proxy_hunter', $username = 'root', $password = '', $unique = false, $type = null)
  {
    // Enforce type to mysql or sqlite when specified
    if ($type === 'mysql') {
      $this->mysql($host, $dbname, $username, $password, $unique);
      $this->driver = 'mysql';
      return;
    } elseif ($type === 'sqlite') {
      $this->sqlite($dbLocation);
      $this->driver = 'sqlite';
      return;
    }
    try {
      $this->mysql($host, $dbname, $username, $password, $unique);
      $this->driver = 'mysql';
    } catch (\Throwable $th) {
      $this->sqlite($dbLocation);
      $this->driver = 'sqlite';
    }
  }

  /**
   * Initialize MySQL database connection and schema.
   *
   * @param string $host
   * @param string $dbname
   * @param string $username
   * @param string $password
   * @param bool   $unique
   */
  public function mysql($host, $dbname, $username, $password, $unique = false)
  {
    $this->db = new MySQLHelper($host, $dbname, $username, $password, $unique);

    // Initialize the database schema
    $sqlFileContents = file_get_contents(__DIR__ . '/assets/mysql-schema.sql');
    $this->db->pdo->exec($sqlFileContents);
  }

  /**
   * Initialize SQLite database connection and schema.
   *
   * @param string|null $dbLocation
   */
  public function sqlite($dbLocation = null)
  {
    if (!$dbLocation) {
      $dbLocation = __DIR__ . '/../database.sqlite';
    } elseif (!file_exists($dbLocation)) {
      // Extract the directory part from the path
      $directory = dirname($dbLocation);
      // Check if the directory exists and create it if it doesn't
      if (!is_dir($directory)) {
        if (!mkdir($directory, 0755, true)) {
          die("Failed to create directory: $directory\n");
        }
      }
    }

    $this->db = new SQLiteHelper($dbLocation);

    // Initialize the database schema
    $sqlFileContents = file_get_contents(__DIR__ . '/assets/sqlite-schema.sql');
    $this->db->pdo->exec($sqlFileContents);

    // Fix journal mode to WAL
    $wal_status = $this->db->pdo->query("PRAGMA journal_mode")->fetch(\PDO::FETCH_ASSOC);
    if (isset($wal_status['journal_mode']) && $wal_status['journal_mode'] !== 'wal') {
      $this->db->pdo->exec("PRAGMA journal_mode = WAL;");
    }
  }

  /**
   * Close database connection.
   */
  public function close()
  {
    if ($this->db) {
      $this->db->close();
    }
  }

  public function __destruct()
  {
    $this->close();
  }

  /**
   * Execute custom SQL query.
   *
   * @param string $sql
   * @param array  $params
   * @return mixed
   */
  public function query($sql, $params = [])
  {
    return $this->db->executeCustomQuery($sql, $params);
  }

  /**
   * Select from database.
   *
   * @param string $table
   * @param array  $columns
   * @param array  $where
   * @param array  $params
   * @param string $orderBy
   * @param int    $limit
   * @param int    $offset
   * @return mixed
   */
  public function select($table, $columns = ['*'], $where = [], $params = [], $orderBy = '', $limit = 0, $offset = 0)
  {
    return $this->db->select($table, $columns, $where, $params, $orderBy, $limit, $offset);
  }
}
