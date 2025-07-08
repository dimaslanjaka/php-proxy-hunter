<?php

use PHPUnit\Framework\TestCase;

/**
 * GetSmsTest
 *
 * PHPUnit test class for verifying the behavior of the `get_sms.php` backend script
 * with different HTTP request methods and content types.
 *
 * Tests performed:
 * - GET request with query string.
 * - POST request with form data.
 * - POST request with JSON body.
 *
 * Prerequisites:
 * - PHP server running at http://localhost:8000/php_backend/get_sms.php
 *   (Start with: php -S localhost:8000)
 * - PHPUnit installed via Composer.
 *
 * Usage:
 *   ./vendor/bin/phpunit tests/GetSmsTest.php
 */
class GetSmsTest extends TestCase
{
  /**
   * The resolved base URL of the PHP backend script.
   *
   * @var string
   */
  private static string $baseUrl;

  /**
   * Called once before running any tests.
   *
   * Detects which base URL is reachable and sets it accordingly.
   */
  public static function setUpBeforeClass(): void
  {
    $urlsToTry = [
      'http://localhost:8000/php_backend/get_sms.php',
      'http://localhost/php_backend/get_sms.php',
    ];

    foreach ($urlsToTry as $url) {
      $headers = @get_headers($url);
      if ($headers && strpos($headers[0], '200') !== false) {
        self::$baseUrl = $url;
        return;
      }
    }

    self::fail("Neither http://localhost:8000 nor http://localhost is available.");
  }

  public function testPostJson()
  {
    $data = ['sms' => 'Hello from JSON'];
    $options = [
      'http' => [
        'method'  => 'POST',
        'header'  => [
          "Content-Type: application/json",
        ],
        'content' => json_encode($data),
      ]
    ];
    $context = stream_context_create($options);
    $response = file_get_contents($this->baseUrl, false, $context);

    $this->assertNotFalse($response);
    $this->assertStringContainsString('Hello from JSON', $response);
  }

  public function testPostForm()
  {
    $data = http_build_query(['sms' => 'Hello from form']);
    $options = [
      'http' => [
        'method'  => 'POST',
        'header'  => [
          "Content-Type: application/x-www-form-urlencoded",
        ],
        'content' => $data,
      ]
    ];
    $context = stream_context_create($options);
    $response = file_get_contents($this->baseUrl, false, $context);

    $this->assertNotFalse($response);
    $this->assertStringContainsString('Hello from form', $response);
  }

  public function testGetRequest()
  {
    $response = file_get_contents($this->baseUrl . '?sms=Hello+from+GET');

    $this->assertNotFalse($response);
    $this->assertStringContainsString('Hello from GET', $response);
  }
}
