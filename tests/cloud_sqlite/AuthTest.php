<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AuthTest extends TestCase
{
  public static function setUpBeforeClass(): void
  {
    // Start PHP built-in server in the background
    shell_exec('php -S localhost:8000 -t cloud_sqlite > /dev/null 2>&1 &');
    // Optionally, wait a moment for the server to start
    sleep(1);
  }

  public function testAuthTokenConstant(): void
  {
    require_once dirname(__DIR__, 2) . '/cloud_sqlite/config.php';
    $this->assertTrue(defined('AUTH_TOKEN'));
    $this->assertNotEmpty(AUTH_TOKEN);
  }
}
