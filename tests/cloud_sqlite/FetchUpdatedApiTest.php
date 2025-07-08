<?php

declare(strict_types=1);

namespace Tests\CloudSqlite;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use PHPUnit\Framework\TestCase;

/**
 * @covers \cloud_sqlite
 */
final class FetchUpdatedApiTest extends TestCase
{
  private static string $baseUrl = 'http://localhost:8000/cloud_sqlite';

  public function testFetchUpdated(): void
  {
    // Insert a row to ensure something is available
    $data = [
        'id' => 100,
        'name' => 'FetchUpdated',
        'value' => 'Test',
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
    file_get_contents(self::$baseUrl . '/sync.php', false, $context);
    $since = date('Y-m-d H:i:s', strtotime('-1 day'));
    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => 'Authorization: Bearer lasjhdfjo',
        ],
    ];
    $context = stream_context_create($opts);
    $url = self::$baseUrl . '/fetch-updated.php?since=' . urlencode($since);
    $result = file_get_contents($url, false, $context);
    if ($result === false) {
      $error = error_get_last();
      $this->fail('HTTP request failed: ' . ($error['message'] ?? 'Unknown error'));
    }
    $rows = json_decode($result, true);
    if ($rows === null) {
      $this->fail('Response is not valid JSON: ' . $result);
    }
    $this->assertIsArray($rows);
    $this->assertNotEmpty($rows);
  }
}
