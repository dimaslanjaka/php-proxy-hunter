<?php

define('PHP_PROXY_HUNTER', true);

use PHPUnit\Framework\TestCase;
use PhpProxyHunter\MySQLHelper;

class MySQLHelperTest extends TestCase
{
  private MySQLHelper $db;
  private string $mysqlHost;
  private string $mysqlUser;
  private string $mysqlPass;
  private string $mysqlDb;
  private string $table = 'test_table';

  protected function setUp(): void
  {
    $this->mysqlHost = $_ENV['MYSQL_HOST'] ?? getenv('MYSQL_HOST');
    $this->mysqlUser = $_ENV['MYSQL_USER'] ?? getenv('MYSQL_USER');
    $this->mysqlPass = $_ENV['MYSQL_PASS'] ?? getenv('MYSQL_PASS');
    $this->mysqlDb   = 'php_proxy_hunter_test';
    $this->db        = new MySQLHelper(
      $this->mysqlHost,
      $this->mysqlDb,
      $this->mysqlUser,
      $this->mysqlPass,
      true
    );
    $this->db->pdo->exec("DROP TABLE IF EXISTS `{$this->table}`");
    $this->db->pdo->exec(
      "CREATE TABLE `{$this->table}` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255),
        age INT
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
    );
  }

  protected function tearDown(): void
  {
    if (isset($this->db) && $this->db->pdo) {
      $this->db->pdo->exec("DROP TABLE IF EXISTS `{$this->table}`");
    }
  }

  public function testInsertAndSelect(): void
  {
    $this->db->insert($this->table, ['name' => 'Alice', 'age' => 30]);
    $results = $this->db->select($this->table);

    $this->assertCount(1, $results);
    $this->assertSame('Alice', $results[0]['name']);
    $this->assertSame(30, (int)$results[0]['age']);
  }

  public function testCount(): void
  {
    $this->db->insert($this->table, ['name' => 'Bob', 'age' => 25]);
    $count = $this->db->count($this->table);
    $this->assertSame(1, $count);
  }

  public function testUpdate(): void
  {
    $this->db->insert($this->table, ['name' => 'Charlie', 'age' => 40]);
    $this->db->update($this->table, ['age' => 41], 'name = ?', ['Charlie']);

    $results = $this->db->select($this->table, '*', 'name = ?', ['Charlie']);
    $this->assertSame(41, (int)$results[0]['age']);
  }

  public function testDelete(): void
  {
    $this->db->insert($this->table, ['name' => 'Dave', 'age' => 50]);
    $this->db->delete($this->table, 'name = ?', ['Dave']);

    $results = $this->db->select($this->table);
    $this->assertCount(0, $results);
  }

  public function testExecuteCustomQuery(): void
  {
    $this->db->insert($this->table, ['name' => 'Eve', 'age' => 22]);
    $stmt = $this->db->pdo->query("SELECT COUNT(*) as cnt FROM `{$this->table}` WHERE name = 'Eve'");
    $row  = $stmt->fetch();
    $this->assertSame(1, (int)$row['cnt']);
  }
}
