<?php

use PHPUnit\Framework\TestCase;

class BlacklistTest extends TestCase
{
  private $dataFile;
  private $backupFile;

  protected function setUp(): void
  {
    $this->dataFile = __DIR__ . '/../../data/blacklist.conf';
    if (file_exists($this->dataFile)) {
      $this->backupFile = sys_get_temp_dir() . '/blacklist.conf.bak.' . uniqid();
      rename($this->dataFile, $this->backupFile);
    } else {
      $this->backupFile = null;
    }

    file_put_contents($this->dataFile, "1.2.3.4\n2001:db8::1\n");

    require_once __DIR__ . '/../../php_backend/shared.php';
    require_once __DIR__ . '/../../src/blacklist.php';
  }

  protected function tearDown(): void
  {
    if (file_exists($this->dataFile)) {
      unlink($this->dataFile);
    }
    if (!empty($this->backupFile) && file_exists($this->backupFile)) {
      rename($this->backupFile, $this->dataFile);
    }
  }

  public function testIPv4IsBlacklisted()
  {
    $this->assertTrue(is_blacklist('1.2.3.4'));
  }

  public function testIPv4WithPortIsBlacklisted()
  {
    $this->assertTrue(is_blacklist('1.2.3.4:8080'));
  }

  public function testIPv6BracketedIsBlacklisted()
  {
    $this->assertTrue(is_blacklist('[2001:db8::1]:8080'));
  }

  public function testIPv6RawIsBlacklisted()
  {
    $this->assertTrue(is_blacklist('2001:db8::1'));
  }

  public function testNotBlacklistedReturnsFalse()
  {
    $this->assertFalse(is_blacklist('5.6.7.8'));
  }

  public function testInvalidProxyReturnsFalse()
  {
    $this->assertFalse(is_blacklist('not-an-ip'));
  }
}
