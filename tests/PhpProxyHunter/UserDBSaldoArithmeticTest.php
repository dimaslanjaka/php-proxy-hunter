<?php

declare(strict_types=1);
define('PHP_PROXY_HUNTER', true);

use PHPUnit\Framework\TestCase;
use PhpProxyHunter\UserDB;

/**
 * @covers \PhpProxyHunter\UserDB
 */
class UserDBSaldoArithmeticTest extends TestCase
{
  private string $mysqlHost;
  private string $mysqlDb;
  private string $mysqlUser;
  private string $mysqlPass;

  public function __construct(?string $name = null, array $data = [], $dataName = '')
  {
    parent::__construct($name, $data, $dataName);
    $this->mysqlHost = $_ENV['MYSQL_HOST'] ?? 'localhost';
    $this->mysqlDb   = 'php_proxy_hunter_test';
    $this->mysqlUser = $_ENV['MYSQL_USER'] ?? '';
    $this->mysqlPass = $_ENV['MYSQL_PASS'] ?? '';
    // Assert that MySQL password is not empty for test safety
    if (empty($this->mysqlPass)) {
      throw new \RuntimeException('MYSQL_PASS environment variable must not be empty for MySQL tests.');
    }
  }

  public function testSaldoArithmeticSqlite(): void
  {
    $testDbPath = sys_get_temp_dir() . '/test_database_saldo.sqlite';
    if (file_exists($testDbPath)) {
      @unlink($testDbPath);
    }
    $userDB = new UserDB($testDbPath);
    $this->runSaldoArithmeticTest($userDB);
    $userDB->close();
    if (file_exists($testDbPath)) {
      @unlink($testDbPath);
    }
  }

  public function testSaldoArithmeticMysql(): void
  {
    try {
      $userDB = new UserDB(null, 'mysql', $this->mysqlHost, $this->mysqlDb, $this->mysqlUser, $this->mysqlPass, true);
      $pdo = $userDB->db->pdo;
      $pdo->exec('SET FOREIGN_KEY_CHECKS=0;');
      $pdo->exec('TRUNCATE TABLE user_logs;');
      $pdo->exec('TRUNCATE TABLE user_fields;');
      $pdo->exec('TRUNCATE TABLE auth_user;');
      $pdo->exec('TRUNCATE TABLE meta;');
      $pdo->exec('TRUNCATE TABLE added_proxies;');
      $pdo->exec('TRUNCATE TABLE processed_proxies;');
      $pdo->exec('TRUNCATE TABLE proxies;');
      $pdo->exec('SET FOREIGN_KEY_CHECKS=1;');
      $this->runSaldoArithmeticTest($userDB);
      $userDB->close();
    } catch (\Throwable $e) {
      $this->fail("Failed to connect or setup MySQL: " . $e->getMessage());
    }
  }

  private function runSaldoArithmeticTest(UserDB $userDB): void
  {
    $userData = [
      'username' => 'arithuser',
      'password' => 'arithpass',
      'email' => 'arith@example.com',
    ];
    $userDB->add($userData);
    $user = $userDB->select('arithuser');
    $id = (int) $user['id'];

    // Initial saldo should be 0 after first insert
    $saldo = $userDB->get_saldo($id);
    $this->assertEquals(0, $saldo);

    // Add 200
    $userDB->update_saldo($id, 200, 'test_add');
    $saldo = $userDB->get_saldo($id);
    $this->assertEquals(200, $saldo);

    // Add 50 more
    $userDB->update_saldo($id, 50, 'test_plus');
    $saldo = $userDB->get_saldo($id);
    $this->assertEquals(250, $saldo);

    // Subtract 100
    $userDB->update_saldo($id, -100, 'test_minus');
    $saldo = $userDB->get_saldo($id);
    $this->assertEquals(150, $saldo);

    // Subtract 200 (should allow negative saldo)
    $userDB->update_saldo($id, -200, 'test_minus_overdraw');
    $saldo = $userDB->get_saldo($id);
    $this->assertEquals(-50, $saldo);
  }
}
