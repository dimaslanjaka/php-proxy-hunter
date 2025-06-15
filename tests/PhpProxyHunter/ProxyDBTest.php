<?php

define('PHP_PROXY_HUNTER', true);

use PHPUnit\Framework\TestCase;
use PhpProxyHunter\ProxyDB;

class ProxyDBTest extends TestCase
{
  /** @var ProxyDB */
  private $proxyDB;

  /** @var string */
  private $testProxy = '123.123.123.123:8080';

  protected function setUp(): void
  {
    // Use an in-memory SQLite DB for isolation
    $this->proxyDB = new ProxyDB(':memory:');
  }

  protected function tearDown(): void
  {
    // Remove test proxy if exists
    $this->proxyDB->remove($this->testProxy);
    $this->proxyDB->close();
  }

  public function testAddAndSelectProxy(): void
  {
    $this->proxyDB->add($this->testProxy);
    $result = $this->proxyDB->select($this->testProxy);

    $this->assertNotEmpty($result);
    $this->assertEquals($this->testProxy, $result[0]['proxy']);
  }

  public function testUpdateProxy(): void
  {
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
  }

  public function testRemoveProxy(): void
  {
    $this->proxyDB->add($this->testProxy);
    $this->proxyDB->remove($this->testProxy);
    $result = $this->proxyDB->select($this->testProxy);

    $this->assertEmpty($result);
  }

  public function testIsAlreadyAddedAndMarkAsAdded(): void
  {
    $this->assertFalse($this->proxyDB->isAlreadyAdded($this->testProxy));
    $this->proxyDB->markAsAdded($this->testProxy);
    $this->assertTrue($this->proxyDB->isAlreadyAdded($this->testProxy));
  }

  public function testGetAllProxies(): void
  {
    $this->proxyDB->add($this->testProxy);
    $proxies = $this->proxyDB->getAllProxies();
    $this->assertNotEmpty($proxies);
  }
}
