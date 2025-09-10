<?php

use PHPUnit\Framework\TestCase;
use PhpProxyHunter\CoreDB;

/**
 * @covers LogsRepository
 */
class LogsRepositoryTest extends TestCase
{
  /**
   * @var LogsRepository
   */
  private $logsRepository;

  /**
   * @var string|null
   */
  private $mysqlHost;

  /**
   * @var string|null
   */
  private $mysqlUser;

  /**
   * @var string|null
   */
  private $mysqlPass;

  /**
   * @var string|null
   */
  private $mysqlDb;

  /**
   * @var CoreDB|null
   */
  private $db;

  /**
   * @var string|null
   */
  private $testDbPath;

  public function dbProvider()
  {
    return [
      'sqlite' => ['sqlite'],
      'mysql'  => ['mysql'],
    ];
  }

  protected function setUp(): void
  {
    $this->mysqlHost = $_ENV['MYSQL_HOST'] ?? getenv('DB_HOST');
    $this->mysqlUser = $_ENV['MYSQL_USER'] ?? getenv('DB_USER');
    $this->mysqlPass = $_ENV['MYSQL_PASS'] ?? getenv('DB_PASS');
    $this->mysqlDb   = 'php_proxy_hunter_test';
  }

  protected function setUpDB($driver)
  {
    if ($driver === 'mysql') {
      $this->db = new CoreDB(
        null,
        $this->mysqlHost,
        $this->mysqlDb,
        $this->mysqlUser,
        $this->mysqlPass,
        true,
        'mysql'
      );
    } else {
      $this->testDbPath = sys_get_temp_dir() . '/test_packages.sqlite';
      $this->db         = new CoreDB($this->testDbPath);
    }

    $this->logsRepository = new LogsRepository($this->db->db->pdo);
  }

  /**
   * @dataProvider dbProvider
   */
  public function testAddLogAndGetLogsFromDb($driver)
  {
    $this->setUpDB($driver);
    $result = $this->logsRepository->addLog(1, 'Test log', 'INFO', 'test', ['foo' => 'bar']);
    $this->assertTrue($result);
    $logs = $this->logsRepository->getLogsFromDb(1);
    $this->assertCount(1, $logs);
    $this->assertEquals('Test log', $logs[0]['message']);
  }

  /**
   * @dataProvider dbProvider
   */
  public function testAddActivityAndGetActivities($driver)
  {
    $this->setUpDB($driver);
    $result = $this->logsRepository->addActivity(2, 'LOGIN', 'user', 123, '127.0.0.1', 'PHPUnit', ['ip' => '127.0.0.1']);
    $this->assertTrue($result);
    $activities = $this->logsRepository->getActivities(1);
    $this->assertCount(1, $activities);
    $this->assertEquals('LOGIN', $activities[0]['activity_type']);
  }

  /**
   * @dataProvider dbProvider
   */
  public function testGetLogsByHashReturnsNullIfFileNotExists($driver)
  {
    $this->setUpDB($driver);
    $hash   = 'nonexistenthash';
    $result = $this->logsRepository->getLogsByHash($hash);
    $this->assertNull($result);
  }

  /**
   * @dataProvider dbProvider
   */
  public function testGetLogsByHashReturnsContentIfFileExists($driver)
  {
    $this->setUpDB($driver);
    $hash = 'testhash';

    // Write log using the repository's logic
    $this->logsRepository->addLogByHash($hash, 'log content');
    $result = $this->logsRepository->getLogsByHash($hash);
    $this->assertEquals('log content', $result);

    // Cleanup
    $file = $this->logsRepository->getLogFilePath($hash);
    if ($file && file_exists($file)) {
      unlink($file);
    }
  }
}
