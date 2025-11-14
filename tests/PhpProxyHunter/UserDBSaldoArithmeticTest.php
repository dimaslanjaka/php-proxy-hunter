<?php

declare(strict_types=1);
define('PHP_PROXY_HUNTER', true);

use PHPUnit\Framework\TestCase;
use PhpProxyHunter\UserDB;

/**
 * @covers \PhpProxyHunter\UserDB
 */
class UserDBSaldoArithmeticTest extends TestCase {
  private string $mysqlHost;
  private string $mysqlDb;
  private string $mysqlUser;
  private string $mysqlPass;

  /**
   * Create the test database if it does not exist.
   */
  private function createTestDatabase(): void {
    $dsn = sprintf('mysql:host=%s', $this->mysqlHost);
    $pdo = new \PDO($dsn, $this->mysqlUser, $this->mysqlPass);
    $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . $this->mysqlDb . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;');
  }

  /**
   * Drop the test database after tests.
   */
  private function dropTestDatabase(): void {
    $dsn = sprintf('mysql:host=%s', $this->mysqlHost);
    $pdo = new \PDO($dsn, $this->mysqlUser, $this->mysqlPass);
    $pdo->exec('DROP DATABASE IF EXISTS `' . $this->mysqlDb . '`;');
  }

  public function __construct(?string $name = null, array $data = [], $dataName = '') {
    parent::__construct($name, $data, $dataName);
    $this->mysqlHost = $_ENV['MYSQL_HOST'] ?? 'localhost';
    $this->mysqlDb   = 'phpunit_test_db';
    $this->mysqlUser = $_ENV['MYSQL_USER'] ?? '';
    $this->mysqlPass = $_ENV['MYSQL_PASS'] ?? '';
    // Assert that MySQL password is not empty for test safety
    if (empty($this->mysqlPass)) {
      throw new \RuntimeException('MYSQL_PASS environment variable must not be empty for MySQL tests.');
    }
  }


  public function dbProvider(): array {
    return [
      'sqlite' => ['sqlite'],
      'mysql'  => ['mysql'],
    ];
  }

  /**
   * @dataProvider dbProvider
   */
  public function testSaldoArithmetic($driver): void {
    if ($driver === 'sqlite') {
      $testDbPath = __DIR__ . '/tmp/test_database_saldo.sqlite';
      if (file_exists($testDbPath)) {
        @unlink($testDbPath);
      }
      $userDB = new UserDB($testDbPath);
      $this->runSaldoArithmeticTest($userDB);
      $userDB->close();
      if (file_exists($testDbPath)) {
        @unlink($testDbPath);
      }
    } else {
      try {
        $this->createTestDatabase();
        $userDB = new UserDB(null, 'mysql', $this->mysqlHost, $this->mysqlDb, $this->mysqlUser, $this->mysqlPass, true);
        $pdo    = $userDB->db->pdo;
        foreach (['user_fields', 'auth_user', 'meta', 'added_proxies', 'processed_proxies', 'proxies'] as $table) {
          $pdo->exec("TRUNCATE TABLE $table;");
        }
        $this->runSaldoArithmeticTest($userDB);
        $userDB->close();
        $this->dropTestDatabase();
      } catch (\Throwable $e) {
        $this->fail('Failed to connect or setup MySQL: ' . $e->getMessage());
      }
    }
  }

  private function runSaldoArithmeticTest(UserDB $userDB): void {
    $userData = [
      'username' => 'arithuser',
      'password' => 'arithpass',
      'email'    => 'arith@example.com',
    ];
    // Add user only if not exists
    if (!$userDB->select($userData['username'])) {
      $userDB->add($userData);
    }
    $user = $userDB->select('arithuser');
    $id   = (int) $user['id'];

    // Initial saldo should be 0 after first insert
    $saldo = $userDB->getPoint($id);
    $this->assertEquals(0, $saldo);

    // Add 200
    $userDB->updatePoint($id, 200, 'test_add', '', false);
    $saldo = $userDB->getPoint($id);
    $this->assertEquals(200, $saldo);

    // Add 50 more
    $userDB->updatePoint($id, 50, 'test_plus', '', false);
    $saldo = $userDB->getPoint($id);
    $this->assertEquals(250, $saldo);

    // Subtract 100
    $userDB->updatePoint($id, -100, 'test_minus', '', false);
    $saldo = $userDB->getPoint($id);
    $this->assertEquals(150, $saldo);

    // Subtract 200 (should allow negative saldo)
    $userDB->updatePoint($id, -200, 'test_minus_overdraw', '', false);
    $saldo = $userDB->getPoint($id);
    $this->assertEquals(-50, $saldo);

    // Set saldo to 1234 (replace)
    $userDB->updatePoint($id, 1234, 'test_set', '', true);
    $saldo = $userDB->getPoint($id);
    $this->assertEquals(1234, $saldo);
  }
}
