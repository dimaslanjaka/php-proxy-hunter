<?php

use PhpProxyHunter\UserDB;
use PhpProxyHunter\CoreDB;
use PHPUnit\Framework\TestCase;
use PhpProxyHunter\LogsRepository;

/**
 * @covers LogsRepository
 */
class LogsRepositoryTest extends TestCase
{
  /**
   * @var UserDB|null
   */
  private $userDB;
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


  /**
   * @var int
   */
  private $testUserId = 7895;

  /**
   * @var array
   */
  private $testUserInfo = [
    'password'     => 'testpass',
    'last_login'   => null,
    'is_superuser' => 0,
    'username'     => 'testuser',
    'last_name'    => '',
    'email'        => 'testuser@example.com',
    'is_staff'     => 0,
    'is_active'    => 1,
    'date_joined'  => '', // will be set dynamically
    'first_name'   => '',
  ];

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
    // Set up UserDB for user management
    $this->userDB = new UserDB($this->db->db, $driver);
  }

  /**
   * @dataProvider dbProvider
   */
  public function testAddLogAndGetLogsFromDb($driver)
  {
    $this->setUpDB($driver);
    $userId = $this->testUserId;
    if ($driver === 'mysql') {
      $info                = $this->testUserInfo;
      $info['id']          = $userId;
      $info['date_joined'] = date('Y-m-d H:i:s');
      $this->userDB->delete($userId);
      $this->userDB->delete($info['username']);
      $this->userDB->add($info);
    }
    $result = $this->logsRepository->addLog($userId, 'Test log', 'INFO', 'test', ['foo' => 'bar']);
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
    $userId = $this->testUserId;
    if ($driver === 'mysql') {
      $info                = $this->testUserInfo;
      $info['id']          = $userId;
      $info['date_joined'] = date('Y-m-d H:i:s');
      $this->userDB->delete($userId);
      $this->userDB->delete($info['username']);
      $this->userDB->add($info);
    }
    $result = $this->logsRepository->addActivity($userId, 'LOGIN', 'user', 123, '127.0.0.1', 'PHPUnit', ['ip' => '127.0.0.1']);
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
