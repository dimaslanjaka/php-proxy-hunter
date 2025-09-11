<?php

namespace PhpProxyHunter;

use PDO;
use RuntimeException;

class CoreDB
{
  /**
   * Path to SQLite database file (if using SQLite) or null
   * @var string|null
   */
  public $dbLocation = null;
  /**
   * Database host (for MySQL)
   * @var string|null
   */
  public $host = null;
  /**
   * Database name (for MySQL)
   * @var string|null
   */
  public $dbname = null;
  /**
   * Database username (for MySQL)
   * @var string|null
   */
  public $username = null;
  /**
   * Database password (for MySQL)
   * @var string|null
   */
  public $password = null;
  /**
   * Whether to use unique connection (for MySQL)
   * @var bool|null
   */
  public $unique = null;
  /**
   * Database driver type ('mysql' or 'sqlite')
   * @var string|null
   */
  public $type = null;
  /**
   * @var SQLiteHelper|MySQLHelper Database helper instance
   */
  public $db;

  /**
   * @var UserDB|null User database helper instance
   */
  public $user_db = null;

  /**
   * @var ProxyDB|null Proxy database helper instance
   */
  public $proxy_db = null;

  /**
   * @var string|null Database driver type ('mysql' or 'sqlite')
   */
  public $driver = null;

  /**
   * @var string|null Path to SQLite database file
   */
  public $dbPath = null;

  /**
   * @var LogsRepository|null Logs repository instance
   */
  public $logsRepository = null;

  /**
   * CoreDB constructor.
   *
   * Initializes the database connection using MySQL or SQLite.
   * Attempts to connect to MySQL first; falls back to SQLite if MySQL connection fails.
   *
   * @param string|null $dbLocation Path to SQLite database file (if using SQLite), or null.
   * @param string $host Database host for MySQL (default: 'localhost').
   * @param string $dbname Database name for MySQL (default: 'php_proxy_hunter').
   * @param string $username Database username for MySQL (default: 'root').
   * @param string $password Database password for MySQL (default: '').
   * @param bool $unique Whether to use a unique MySQL connection (default: false).
   * @param string|null $type Database driver type ('mysql' or 'sqlite'), or null for auto-detect.
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
    $this->dbLocation = $dbLocation;
    $this->host       = $host;
    $this->dbname     = $dbname;
    $this->username   = $username;
    $this->password   = $password;
    $this->unique     = $unique;
    $this->type       = $type;

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



  /**
   * Initialize MySQL database connection and schema.
   */
  private function initMySQL($host, $dbname, $username, $password, $unique = false)
  {
    $this->db             = new MySQLHelper($host, $dbname, $username, $password, $unique);
    $this->driver         = 'mysql';
    $this->user_db        = new UserDB($this->db);
    $this->proxy_db       = new ProxyDB($this->db);
    $this->logsRepository = new LogsRepository($this->db->pdo);

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

    $this->dbPath   = $dbLocation;
    $this->db       = new SQLiteHelper($dbLocation);
    $this->driver   = 'sqlite';
    $this->user_db  = new UserDB($this->db);
    $this->proxy_db = new ProxyDB($this->db);

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
    return $this->db->execute($sql, $params);
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
