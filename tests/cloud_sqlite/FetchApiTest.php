<?php

declare(strict_types=1);

namespace Tests\CloudSqlite;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use PHPUnit\Framework\TestCase;

/**
 * @covers \cloud_sqlite
 */
final class FetchApiTest extends TestCase
{
  private static string $baseUrl = 'http://localhost:8000/cloud_sqlite';

  public function testFetchAll(): void
  {
    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => 'Authorization: Bearer lasjhdfjo',
        ],
    ];
    $context = stream_context_create($opts);
    $result = file_get_contents(self::$baseUrl . '/fetch.php', false, $context);
    if ($result === false) {
      $error = error_get_last();
      $this->fail('HTTP request failed: ' . ($error['message'] ?? 'Unknown error'));
    }
    $rows = json_decode($result, true);
    if ($rows === null) {
      $this->fail('Response is not valid JSON: ' . $result);
    }
    $this->assertIsArray($rows);
  }
}
