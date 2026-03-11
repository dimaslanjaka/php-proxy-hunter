<?php

use PHPUnit\Framework\TestCase;

class BlacklistTest extends TestCase
{
  private $dataFile;

  protected function setUp(): void
  {
    $this->dataFile = __DIR__ . '/../../data/blacklist.conf';
    // Ensure data directory exists and write a test-only blacklist file
    $dir = dirname($this->dataFile);
    if (!is_dir($dir)) {
      @mkdir($dir, 0777, true);
    }

    file_put_contents($this->dataFile, "1.2.3.4\n2001:db8::1\n");
  }

  protected function tearDown(): void
  {
    if (file_exists($this->dataFile)) {
      @unlink($this->dataFile);
    }
  }

  public function testIPv4IsBlacklisted()
  {
    $this->assertTrue(is_blacklist('1.2.3.4', $this->dataFile));
  }

  public function testIPv4WithPortIsBlacklisted()
  {
    $this->assertTrue(is_blacklist('1.2.3.4:8080', $this->dataFile));
  }

  public function testIPv6BracketedIsBlacklisted()
  {
    $this->assertTrue(is_blacklist('[2001:db8::1]:8080', $this->dataFile));
  }

  public function testIPv6RawIsBlacklisted()
  {
    $this->assertTrue(is_blacklist('2001:db8::1', $this->dataFile));
  }

  public function testNotBlacklistedReturnsFalse()
  {
    $this->assertFalse(is_blacklist('5.6.7.8', $this->dataFile));
  }

  public function testInvalidProxyReturnsFalse()
  {
    $this->assertFalse(is_blacklist('not-an-ip', $this->dataFile));
  }
}
