<?php

namespace PhpProxyHunter;

use PDO;
use RuntimeException;

class CoreDB
{
  /**
   * @var SQLiteHelper|MySQLHelper Database helper instance
   */
  public $db;

  /**
   * @var string|null Database driver type ('mysql' or 'sqlite')
   */
  public ?string $driver = null;

  /**
   * @var string|null Path to SQLite database file
   */
  public ?string $dbPath = null;

  /**
   * CoreDB constructor.
   *
   * Initializes the database connection using MySQL or SQLite.
   * Attempts to connect to MySQL first; falls back to SQLite if MySQL connection fails.
   */
  public function __construct(
    ?string $dbLocation = null,
    string $host = 'localhost',
    string $dbname = 'php_proxy_hunter',
    string $username = 'root',
    string $password = '',
    bool $unique = false,
    ?string $type = null
  ) {
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
    } catch (\Throwable $th) {
      $this->initSQLite($dbLocation);
    }
  }

  /**
   * Initialize MySQL database connection and schema.
   */
  private function initMySQL(string $host, string $dbname, string $username, string $password, bool $unique = false): void
  {
    $this->db = new MySQLHelper($host, $dbname, $username, $password, $unique);
    $this->driver = 'mysql';

    $this->loadSchema(__DIR__ . '/assets/mysql-schema.sql');
  }

  /**
   * Initialize SQLite database connection and schema.
   */
  private function initSQLite(?string $dbLocation = null): void
  {
    $dbLocation ??= __DIR__ . '/../database.sqlite';

    if (!file_exists($dbLocation)) {
      $directory = dirname($dbLocation);
      if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException("Failed to create directory: $directory");
      }
    }

    $this->dbPath = $dbLocation;
    $this->db = new SQLiteHelper($dbLocation);
    $this->driver = 'sqlite';

    $this->loadSchema(__DIR__ . '/assets/sqlite-schema.sql');

    // Ensure WAL mode
    $walStatus = $this->db->pdo->query("PRAGMA journal_mode")->fetch(PDO::FETCH_ASSOC);
    if (($walStatus['journal_mode'] ?? '') !== 'wal') {
      $this->db->pdo->exec("PRAGMA journal_mode = WAL;");
    }
  }

  /**
   * Load and execute schema file if exists.
   */
  private function loadSchema(string $schemaPath): void
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
  public function close(): void
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
  public function query(string $sql, array $params = []): mixed
  {
    return $this->db->executeCustomQuery($sql, $params);
  }

  /**
   * Select from database.
   */
  public function select(
    string $table,
    array $columns = ['*'],
    array $where = [],
    array $params = [],
    string $orderBy = '',
    int $limit = 0,
    int $offset = 0
  ): mixed {
    return $this->db->select($table, $columns, $where, $params, $orderBy, $limit, $offset);
  }
}
