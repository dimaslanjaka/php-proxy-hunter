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

  public function testCreateTable(): void
  {
    $table   = 'test_create_table';
    $columns = [
      'id INT AUTO_INCREMENT PRIMARY KEY',
      'foo VARCHAR(50)',
    ];
    $this->db->createTable($table, $columns);
    $this->assertContains('foo', $this->db->getTableColumns($table));
    $this->db->pdo->exec("DROP TABLE IF EXISTS `$table`");
  }

  public function testClose(): void
  {
    $this->db->close();
    $this->assertNull($this->db->pdo);
  }

  public function testResetIncrement(): void
  {
    $this->db->insert($this->table, ['name' => 'Reset', 'age' => 1]);
    $this->db->pdo->exec("DELETE FROM `{$this->table}`");
    $this->db->resetIncrement($this->table);
    $this->db->insert($this->table, ['name' => 'Reset2', 'age' => 2]);
    $result = $this->db->select($this->table);
    $this->assertSame(1, (int)$result[0]['id']);
  }

  public function testGetTableColumns(): void
  {
    $columns = $this->db->getTableColumns($this->table);
    $this->assertContains('name', $columns);
    $this->assertContains('age', $columns);
  }

  public function testAddColumnIfNotExistsAndColumnExists(): void
  {
    $col = 'new_col';
    $def = 'VARCHAR(20)';
    if ($this->db->columnExists($this->table, $col)) {
      $this->db->dropColumnIfExists($this->table, $col);
    }
    $this->assertFalse($this->db->columnExists($this->table, $col));
    $this->db->addColumnIfNotExists($this->table, $col, $def);
    $this->assertTrue($this->db->columnExists($this->table, $col));
  }

  public function testDropColumnIfExists(): void
  {
    $col = 'to_drop';
    $def = 'INT';
    if (!$this->db->columnExists($this->table, $col)) {
      $this->db->addColumnIfNotExists($this->table, $col, $def);
    }
    $this->assertTrue($this->db->columnExists($this->table, $col));
    $this->db->dropColumnIfExists($this->table, $col);
    $this->assertFalse($this->db->columnExists($this->table, $col));
  }

  public function testConstructorWithPDO(): void
  {
    $pdo = new PDO(
      "mysql:host={$this->mysqlHost};dbname={$this->mysqlDb};charset=utf8mb4",
      $this->mysqlUser,
      $this->mysqlPass
    );
    $db2 = new MySQLHelper($pdo, null, null, null, true);
    $this->assertInstanceOf(MySQLHelper::class, $db2);
    $this->assertInstanceOf(PDO::class, $db2->pdo);
    $db2->pdo->exec('CREATE TABLE IF NOT EXISTS test_pdo (id INT PRIMARY KEY)');
    $db2->pdo->exec('DROP TABLE IF EXISTS test_pdo');
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
