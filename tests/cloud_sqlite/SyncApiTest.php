<?php

declare(strict_types=1);

namespace Tests\CloudSqlite;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use PHPUnit\Framework\TestCase;

/**
 * @covers \cloud_sqlite
 */
final class SyncApiTest extends TestCase
{
  private static string $baseUrl = 'http://localhost:8000/cloud_sqlite';

  public function testInsertOrUpdate(): void
  {
    // Insert
    $data = [
        'id' => 1,
        'name' => 'Device A',
        'value' => 'Hello World',
    ];
    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => [
                'Authorization: Bearer lasjhdfjo',
                'Content-Type: application/json',
            ],
            'content' => json_encode($data),
        ],
    ];
    $context = stream_context_create($opts);
    $result = file_get_contents(self::$baseUrl . '/sync.php', false, $context);
    if ($result === false) {
      $error = error_get_last();
      $this->fail('HTTP request failed: ' . ($error['message'] ?? 'Unknown error'));
    }
    $this->assertStringContainsString('ok', $result);

    // Update
    $data['value'] = 'Updated Value';
    $opts['http']['content'] = json_encode($data);
    $result = file_get_contents(self::$baseUrl . '/sync.php', false, $context);
    if ($result === false) {
      $error = error_get_last();
      $this->fail('HTTP request failed: ' . ($error['message'] ?? 'Unknown error'));
    }
    $this->assertStringContainsString('ok', $result);
  }

  public function testInsertWithAuthParam(): void
  {
    $data = [
        'id' => 2,
        'name' => 'Device B',
        'value' => 'With Auth Param',
    ];
    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => [
                'Content-Type: application/json',
            ],
            'content' => json_encode($data),
        ],
    ];
    $context = stream_context_create($opts);
    $url = self::$baseUrl . '/sync.php?auth=lasjhdfjo';
    $result = file_get_contents($url, false, $context);
    if ($result === false) {
      $error = error_get_last();
      $this->fail('HTTP request failed: ' . ($error['message'] ?? 'Unknown error'));
    }
    $this->assertStringContainsString('ok', $result);
  }

  public function testInsertWithAuthField(): void
  {
    $data = [
        'id' => 3,
        'name' => 'Device C',
        'value' => 'With Auth Field',
        'auth' => 'lasjhdfjo',
    ];
    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => [
                'Content-Type: application/json',
            ],
            'content' => json_encode($data),
        ],
    ];
    $context = stream_context_create($opts);
    $result = file_get_contents(self::$baseUrl . '/sync.php', false, $context);
    if ($result === false) {
      $error = error_get_last();
      $this->fail('HTTP request failed: ' . ($error['message'] ?? 'Unknown error'));
    }
    $this->assertStringContainsString('ok', $result);
  }
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
  // Run PHPUnit programmatically
  if (class_exists(\PHPUnit\TextUI\Command::class)) {
    \PHPUnit\TextUI\Command::main(false);
  } else {
    fwrite(STDERR, "PHPUnit is not installed.\n");
    exit(1);
  }
}
