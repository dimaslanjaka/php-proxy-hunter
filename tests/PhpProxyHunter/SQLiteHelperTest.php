<?php

define('PHP_PROXY_HUNTER', true);

use PHPUnit\Framework\TestCase;
use PhpProxyHunter\SQLiteHelper;

class SQLiteHelperTest extends TestCase
{
  private SQLiteHelper $db;
  private string $table = 'test_table';

  protected function setUp(): void
  {
    $this->db = new SQLiteHelper(':memory:');
    $this->db->createTable($this->table, [
      'id INTEGER PRIMARY KEY',
      'name TEXT',
      'age INTEGER',
    ]);
  }

  public function testIsDatabaseLocked(): void
  {
    $this->assertFalse($this->db->isDatabaseLocked());
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
    $def = 'TEXT';
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
    $def = 'INTEGER';
    if (!$this->db->columnExists($this->table, $col)) {
      $this->db->addColumnIfNotExists($this->table, $col, $def);
    }
    $this->assertTrue($this->db->columnExists($this->table, $col));
    // Ensure all statements are finalized and unset before schema change
    gc_collect_cycles(); // force collection of any lingering PDOStatement
    $this->db->dropColumnIfExists($this->table, $col);
    $this->assertFalse($this->db->columnExists($this->table, $col));
  }

  public function testConstructorWithPDO(): void
  {
    $pdo = new PDO('sqlite::memory:');
    $db2 = new SQLiteHelper($pdo, true);
    $this->assertInstanceOf(SQLiteHelper::class, $db2);
    $this->assertInstanceOf(PDO::class, $db2->pdo);
    $db2->createTable('test_pdo', ['id INTEGER PRIMARY KEY']);
    $columns = $db2->getTableColumns('test_pdo');
    $this->assertContains('id', $columns);
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
    $results = $this->db->executeCustomQuery("SELECT * FROM {$this->table} WHERE name = ?", ['Eve']);

    $this->assertCount(1, $results);
    $this->assertSame('Eve', $results[0]['name']);
  }

  public function testInsertWithInvalidTableNameThrows(): void
  {
    $this->expectException(\InvalidArgumentException::class);
    $this->db->insert('invalid-table-name!', ['name' => 'Test'], true);
  }

  protected function tearDown(): void
  {
    $this->db->close();
  }
}
