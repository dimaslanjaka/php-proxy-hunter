<?php

define('PHP_PROXY_HUNTER', true);

use PHPUnit\Framework\TestCase;
use PhpProxyHunter\ProxyDB;

class ProxyDBTest extends TestCase
{
  /** @var ProxyDB|null */
  private $proxyDB = null;
  /** @var string */
  private $testProxy          = '123.123.123.123:8080';
  private ?string $testDbPath = null;
  private ?string $mysqlHost  = null;
  private ?string $mysqlUser  = null;
  private ?string $mysqlPass  = null;
  private ?string $mysqlDb    = null;

  public function dbProvider(): array
  {
    return [
      'sqlite' => ['sqlite'],
      'mysql'  => ['mysql'],
    ];
  }

  protected function setUp(): void
  {
    parent::setUp();
    $this->mysqlHost = $_ENV['MYSQL_HOST'] ?? getenv('MYSQL_HOST');
    $this->mysqlUser = $_ENV['MYSQL_USER'] ?? getenv('MYSQL_USER');
    $this->mysqlPass = $_ENV['MYSQL_PASS'] ?? getenv('MYSQL_PASS');
    $this->mysqlDb   = 'php_proxy_hunter_test';
  }

  protected function setUpDB(string $driver): void
  {
    if ($driver === 'mysql') {
      $this->proxyDB = new ProxyDB(
        null,
        'mysql',
        $this->mysqlHost,
        $this->mysqlDb,
        $this->mysqlUser,
        $this->mysqlPass,
        true
      );
      // Remove test proxy if exists
      $this->proxyDB->remove($this->testProxy);
    } else {
      $this->testDbPath = __DIR__ . '/tmp/test_proxydb.sqlite';
      $this->proxyDB    = new ProxyDB($this->testDbPath);
      $this->proxyDB->remove($this->testProxy);
    }
  }

  protected function tearDownDB(string $driver): void
  {
    if ($this->proxyDB) {
      $this->proxyDB->remove($this->testProxy);
      $this->proxyDB->close();
      $this->proxyDB = null;
    }
    gc_collect_cycles();
    if ($driver === 'sqlite' && $this->testDbPath && file_exists($this->testDbPath)) {
      @unlink($this->testDbPath);
    }
  }

  /**
   * @dataProvider dbProvider
   */
  public function testAddAndSelectProxy(string $driver): void
  {
    $this->setUpDB($driver);
    $this->proxyDB->add($this->testProxy);
    $result = $this->proxyDB->select($this->testProxy);
    $this->assertNotEmpty($result);
    $this->assertEquals($this->testProxy, $result[0]['proxy']);
    $this->tearDownDB($driver);
  }

  /**
   * @dataProvider dbProvider
   */
  public function testUpdateProxy(string $driver): void
  {
    $this->setUpDB($driver);
    $this->proxyDB->add($this->testProxy);
    $this->proxyDB->update(
      $this->testProxy,
      'http',
      'SomeRegion',
      'SomeCity',
      'SomeCountry',
      'active',
      '123ms',
      'UTC+7'
    );
    $result = $this->proxyDB->select($this->testProxy);
    $this->assertEquals('http', $result[0]['type']);
    $this->assertEquals('SomeCity', $result[0]['city']);
    $this->assertEquals('active', $result[0]['status']);
    $this->tearDownDB($driver);
  }

  /**
   * @dataProvider dbProvider
   */
  public function testRemoveProxy(string $driver): void
  {
    $this->setUpDB($driver);
    $this->proxyDB->add($this->testProxy);
    $this->proxyDB->remove($this->testProxy);
    $result = $this->proxyDB->select($this->testProxy);
    $this->assertEmpty($result);
    $this->tearDownDB($driver);
  }

  /**
   * @dataProvider dbProvider
   */
  public function testIsAlreadyAddedAndMarkAsAdded(string $driver): void
  {
    $this->setUpDB($driver);
    $this->proxyDB->remove($this->testProxy);
    $this->assertFalse($this->proxyDB->isAlreadyAdded($this->testProxy));
    $this->proxyDB->markAsAdded($this->testProxy);
    $this->assertTrue($this->proxyDB->isAlreadyAdded($this->testProxy));
    $this->proxyDB->remove($this->testProxy);
    $this->tearDownDB($driver);
  }

  /**
   * @dataProvider dbProvider
   */
  public function testGetAllProxies(string $driver): void
  {
    $this->setUpDB($driver);
    $this->proxyDB->add($this->testProxy);
    $proxies = $this->proxyDB->getAllProxies();
    $this->assertNotEmpty($proxies);
    $this->tearDownDB($driver);
  }
}
