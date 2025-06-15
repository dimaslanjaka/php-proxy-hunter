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
      'age INTEGER'
    ]);
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
