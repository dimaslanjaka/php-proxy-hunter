<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PhpProxyHunter\ActivityLog;

class ActivityLogTest extends TestCase
{
  private ?PDO $db          = null;
  private ?ActivityLog $log = null;

  private ?string $mysqlHost = null;
  private ?string $mysqlUser = null;
  private ?string $mysqlPass = null;
  private ?string $mysqlDb   = null;

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
    $this->mysqlDb   = 'activity_log_test_db';
  }

  protected function setUpDB(string $driver): void
  {
    if ($driver === 'mysql') {
      // Connect without specifying database to allow creating it if it doesn't exist
      $dsnNoDb  = sprintf('mysql:host=%s', $this->mysqlHost);
      $this->db = new PDO($dsnNoDb, $this->mysqlUser, $this->mysqlPass);
      $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      // create database if not exists
      $this->db->exec("CREATE DATABASE IF NOT EXISTS `{$this->mysqlDb}`");
      // reconnect to the newly created database
      $dsn      = sprintf('mysql:host=%s;dbname=%s', $this->mysqlHost, $this->mysqlDb);
      $this->db = new PDO($dsn, $this->mysqlUser, $this->mysqlPass);
      $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      // initialize ActivityLog
      $this->log = new ActivityLog($this->db);
      $this->db->exec('DELETE FROM activity_log');
    } else {
      $sqliteFile = __DIR__ . '/tmp/test_packages.sqlite';
      if (!is_dir(__DIR__ . '/tmp')) {
        mkdir(__DIR__ . '/tmp', 0777, true);
      }
      $this->db = new PDO('sqlite:' . $sqliteFile);
      $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      $this->log = new ActivityLog($this->db);
    }
  }

  protected function tearDownDB(string $driver): void
  {
    $this->db  = null;
    $this->log = null;
    gc_collect_cycles();
  }


  /**
   * @dataProvider dbProvider
   */
  public function testLogEntryIsInserted(string $driver): void
  {
    $this->setUpDB($driver);
    $result = $this->log->log(
      1,
      'LOGIN',
      null,
      null,
      null,
      ['info' => 'test'],
      '127.0.0.1',
      'UnitTestAgent'
    );
    $this->assertTrue($result);
    $logs = $this->log->recent(1);
    $this->assertCount(1, $logs);
    $this->assertEquals('LOGIN', $logs[0]['action_type']);
    $this->assertEquals('127.0.0.1', $logs[0]['ip_address']);
    $this->tearDownDB($driver);
  }

  /**
   * @dataProvider dbProvider
   */
  public function testRecentReturns(string $driver): void
  {
    $this->setUpDB($driver);
    $logs = $this->log->recent();
    $this->assertIsArray($logs);
    $this->tearDownDB($driver);
  }

  /**
   * @dataProvider dbProvider
   */
  public function testUpdateModifiesRow(string $driver): void
  {
    $this->setUpDB($driver);

    // insert a record
    $result = $this->log->log(
      2,
      'PACKAGE_CREATE',
      10,
      'package',
      null,
      ['points' => 50],
      '10.0.0.1',
      'UnitTestAgent'
    );
    $this->assertTrue($result);

    $logs = $this->log->recent(1);
    $this->assertCount(1, $logs);
    $id = (int)$logs[0]['id'];

    // perform update
    $updateResult = $this->log->update($id, [
      'action_type' => 'PACKAGE_UPDATE',
      'details'     => ['points' => 75, 'note' => 'updated'],
      'ip_address'  => '10.0.0.2',
    ]);
    $this->assertTrue($updateResult);

    $updated = $this->log->recent(1);
    $this->assertEquals('PACKAGE_UPDATE', $updated[0]['action_type']);
    // details stored as JSON/text
    $this->assertStringContainsString('updated', (string)$updated[0]['details']);
    $this->assertEquals('10.0.0.2', $updated[0]['ip_address']);
    $this->assertEquals(75, json_decode((string)$updated[0]['details'], true)['points']);

    $this->tearDownDB($driver);
  }
}
