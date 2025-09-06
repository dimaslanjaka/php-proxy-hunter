<?php

namespace PhpProxyHunter;

use PDO;
use RuntimeException;

class CoreDB
{
  /**
   * @var string|null
   */
  private $constructorDbLocation = null;
  /**
   * @var string|null
   */
  private $constructorHost = null;
  /**
   * @var string|null
   */
  private $constructorDbName = null;
  /**
   * @var string|null
   */
  private $constructorUsername = null;
  /**
   * @var string|null
   */
  private $constructorPassword = null;
  /**
   * @var bool|null
   */
  private $constructorUnique = null;
  /**
   * @var string|null
   */
  private $constructorType = null;
  /**
   * @var SQLiteHelper|MySQLHelper Database helper instance
   */
  public $db;

  /**
   * @var UserDB|null User database helper instance
   */
  public $user_db = null;

  /**
   * @var string|null Database driver type ('mysql' or 'sqlite')
   */
  public $driver = null;

  /**
   * @var string|null Path to SQLite database file
   */
  public $dbPath = null;

  /**
   * CoreDB constructor.
   *
   * Initializes the database connection using MySQL or SQLite.
   * Attempts to connect to MySQL first; falls back to SQLite if MySQL connection fails.
   */
  public function __construct(
    $dbLocation = null,
    $host = 'localhost',
    $dbname = 'php_proxy_hunter',
    $username = 'root',
    $password = '',
    $unique = false,
    $type = null
  ) {
    $this->constructorDbLocation = $dbLocation;
    $this->constructorHost       = $host;
    $this->constructorDbName     = $dbname;
    $this->constructorUsername   = $username;
    $this->constructorPassword   = $password;
    $this->constructorUnique     = $unique;
    $this->constructorType       = $type;

    // Enforce type when specified
    if ($type === 'mysql') {
      $this->initMySQL($host, $dbname, $username, $password, $unique);
      return;
    }

    if ($type === 'sqlite') {
      $this->initSQLite($dbLocation);
      return;
    }

    // Auto-detect: try MySQL first, then fallback to SQLite
    try {
      $this->initMySQL($host, $dbname, $username, $password, $unique);
    } catch (\Exception $e) {
      $this->initSQLite($dbLocation);
    }
  }

  public function getConstructorDbLocation()
  {
    return $this->constructorDbLocation;
  }

  public function getConstructorHost()
  {
    return $this->constructorHost;
  }

  public function getConstructorDbName()
  {
    return $this->constructorDbName;
  }

  public function getConstructorUsername()
  {
    return $this->constructorUsername;
  }

  public function getConstructorPassword()
  {
    return $this->constructorPassword;
  }

  public function getConstructorUnique()
  {
    return $this->constructorUnique;
  }

  public function getConstructorType()
  {
    return $this->constructorType;
  }

  /**
   * Initialize MySQL database connection and schema.
   */
  private function initMySQL($host, $dbname, $username, $password, $unique = false)
  {
    $this->db      = new MySQLHelper($host, $dbname, $username, $password, $unique);
    $this->driver  = 'mysql';
    $this->user_db = new UserDB(null, 'mysql', $host, $dbname, $username, $password, $unique);

    $this->loadSchema(__DIR__ . '/assets/mysql-schema.sql');
  }

  /**
   * Initialize SQLite database connection and schema.
   */
  private function initSQLite($dbLocation = null)
  {
    if ($dbLocation === null) {
      $dbLocation = __DIR__ . '/../database.sqlite';
    }

    if (!file_exists($dbLocation)) {
      $directory = dirname($dbLocation);
      if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException("Failed to create directory: $directory");
      }
    }

    $this->dbPath  = $dbLocation;
    $this->db      = new SQLiteHelper($dbLocation);
    $this->driver  = 'sqlite';
    $this->user_db = new UserDB($dbLocation, 'sqlite');

    $this->loadSchema(__DIR__ . '/assets/sqlite-schema.sql');

    // Ensure WAL mode
    $walStatus = $this->db->pdo->query('PRAGMA journal_mode')->fetch(PDO::FETCH_ASSOC);
    if (!isset($walStatus['journal_mode']) || strtolower($walStatus['journal_mode']) !== 'wal') {
      $this->db->pdo->exec('PRAGMA journal_mode = WAL;');
    }
  }

  /**
   * Load and execute schema file if exists.
   */
  private function loadSchema($schemaPath)
  {
    if (!is_file($schemaPath)) {
      return;
    }
    $sql = file_get_contents($schemaPath);
    if ($sql !== false) {
      $this->db->pdo->exec($sql);
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
   */
  public function query($sql, $params = [])
  {
    return $this->db->executeCustomQuery($sql, $params);
  }

  /**
   * Select from database.
   */
  public function select(
    $table,
    $columns = ['*'],
    $where = [],
    $params = [],
    $orderBy = '',
    $limit = 0,
    $offset = 0
  ) {
    return $this->db->select($table, $columns, $where, $params, $orderBy, $limit, $offset);
  }
}
