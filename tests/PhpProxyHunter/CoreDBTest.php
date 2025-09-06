<?php

declare(strict_types=1);
define('PHP_PROXY_HUNTER', true);

use PHPUnit\Framework\TestCase;
use PhpProxyHunter\CoreDB;

/**
 * @covers \PhpProxyHunter\CoreDB
 */
class CoreDBTest extends TestCase
{
  /** @var CoreDB|null */
  private $coreDB = null;
  /** @var string|null */
  private $testDbPath = null;
  /** @var string|null */
  private $mysqlHost;
  /** @var string|null */
  private $mysqlUser;
  /** @var string|null */
  private $mysqlPass;
  /** @var string|null */
  private $mysqlDb;

  public function dbProvider(): array
  {
    return [
      'sqlite' => ['sqlite'],
      'mysql'  => ['mysql'],
    ];
  }

  protected function setUp(): void
  {
    $this->mysqlHost = $_ENV['MYSQL_HOST'] ?? null;
    $this->mysqlUser = $_ENV['MYSQL_USER'] ?? null;
    $this->mysqlPass = $_ENV['MYSQL_PASS'] ?? null;
    $this->mysqlDb   = 'php_proxy_hunter_test';
  }

  protected function setUpDB(string $driver): void
  {
    if ($driver === 'mysql') {
      $this->coreDB = new CoreDB(
        null,
        $this->mysqlHost,
        $this->mysqlDb,
        $this->mysqlUser,
        $this->mysqlPass,
        true,
        'mysql'
      );

      // Clean tables before each test
      $pdo = $this->coreDB->db->pdo;
      $pdo->exec('SET FOREIGN_KEY_CHECKS=0;');
      foreach (
        [
          'user_logs',
          'user_fields',
          'auth_user',
          'meta',
          'added_proxies',
          'processed_proxies',
          'proxies',
        ] as $table
      ) {
        $pdo->exec("TRUNCATE TABLE {$table};");
      }
      $pdo->exec('SET FOREIGN_KEY_CHECKS=1;');
    } else {
      $this->testDbPath = sys_get_temp_dir() . '/test_core_database.sqlite';
      $this->coreDB     = new CoreDB($this->testDbPath, null, null, null, null, false, 'sqlite');
    }
  }

  protected function tearDownDB(string $driver): void
  {
    if ($this->coreDB) {
      $this->coreDB->close();
      $this->coreDB = null;
    }
    gc_collect_cycles();

    if ($driver === 'sqlite' && $this->testDbPath && file_exists($this->testDbPath)) {
      @unlink($this->testDbPath);
    }
  }

  /**
   * @dataProvider dbProvider
   */
  public function testClassExists(string $driver): void
  {
    $this->setUpDB($driver);
    try {
      $this->assertTrue(class_exists(CoreDB::class));
    } finally {
      $this->tearDownDB($driver);
    }
  }

  /**
   * @dataProvider dbProvider
   */
  public function testConstructorAndDriver(string $driver): void
  {
    $this->setUpDB($driver);
    try {
      $this->assertInstanceOf(CoreDB::class, $this->coreDB);
      $this->assertEquals($driver, $this->coreDB->driver);
    } finally {
      $this->tearDownDB($driver);
    }
  }

  /**
   * @dataProvider dbProvider
   */
  public function testQueryAndSelect(string $driver): void
  {
    $this->setUpDB($driver);
    try {
      // Insert a row into meta table
      if ($driver === 'mysql') {
        $insertSql     = 'INSERT INTO meta (`key`, value) VALUES (:key, :value)';
        $selectColumns = '`key`, value';
        $where         = '`key` = :key';
      } else {
        $insertSql     = 'INSERT INTO meta ("key", value) VALUES (:key, :value)';
        $selectColumns = 'key, value';
        $where         = 'key = :key';
      }

      $params = [':key' => 'testkey', ':value' => 'testvalue'];
      $this->coreDB->query($insertSql, $params);

      $result = $this->coreDB->select('meta', $selectColumns, $where, [':key' => 'testkey']);

      $this->assertNotEmpty($result);
      $this->assertEquals('testkey', $result[0]['key']);
      $this->assertEquals('testvalue', $result[0]['value']);
    } finally {
      $this->tearDownDB($driver);
    }
  }
}
