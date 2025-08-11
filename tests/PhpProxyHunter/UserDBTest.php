<?php

declare(strict_types=1);
define('PHP_PROXY_HUNTER', true);

use PHPUnit\Framework\TestCase;
use PhpProxyHunter\UserDB;

/**
 * @covers \PhpProxyHunter\UserDB
 */
class UserDBTest extends TestCase
{
  private UserDB $userDB;
  private string $testDbPath;
  private string $mysqlHost = 'localhost';
  private string $mysqlDb = 'php_proxy_hunter_test';
  private string $mysqlUser = 'root';
  private string $mysqlPass = '';
  private bool $useMySQL = false;

  protected function setUp(): void
  {
    $this->useMySQL = getenv('PHP_PROXY_HUNTER_TEST_MYSQL') === '1';
    if ($this->useMySQL) {
      $this->userDB = new UserDB(null, 'mysql', $this->mysqlHost, $this->mysqlDb, $this->mysqlUser, $this->mysqlPass, true);
      // Optionally, clear tables before each test
      $pdo = $this->userDB->db->pdo;
      $pdo->exec('SET FOREIGN_KEY_CHECKS=0;');
      $pdo->exec('TRUNCATE TABLE user_logs;');
      $pdo->exec('TRUNCATE TABLE user_fields;');
      $pdo->exec('TRUNCATE TABLE auth_user;');
      $pdo->exec('TRUNCATE TABLE meta;');
      $pdo->exec('TRUNCATE TABLE added_proxies;');
      $pdo->exec('TRUNCATE TABLE processed_proxies;');
      $pdo->exec('TRUNCATE TABLE proxies;');
      $pdo->exec('SET FOREIGN_KEY_CHECKS=1;');
    } else {
      $this->testDbPath = sys_get_temp_dir() . '/test_database.sqlite';
      if (file_exists($this->testDbPath)) {
        @unlink($this->testDbPath);
      }
      $this->userDB = new UserDB($this->testDbPath);
    }
  }

  protected function tearDown(): void
  {
    $this->userDB->close();
    gc_collect_cycles();
    if (!$this->useMySQL && isset($this->testDbPath) && file_exists($this->testDbPath)) {
      @unlink($this->testDbPath);
    }
  }

  public function testAddAndSelectUser(): void
  {
    $userData = [
      'username' => 'testuser',
      'password' => 'password123',
      'email' => 'test@example.com',
      'first_name' => 'Test',
      'last_name' => 'User',
    ];
    $result = $this->userDB->add($userData);
    $this->assertTrue($result);

    $selected = $this->userDB->select('testuser');
    $this->assertNotEmpty($selected);
    $this->assertEquals('testuser', $selected['username']);
    $this->assertEquals('test@example.com', $selected['email']);
  }

  public function testUpdateUser(): void
  {
    $userData = [
      'username' => 'updateuser',
      'password' => 'password123',
      'email' => 'update@example.com',
    ];
    $this->userDB->add($userData);
    $updateData = [
      'first_name' => 'Updated',
      'last_name' => 'Name',
    ];
    // Test update by username
    $resultUsername = $this->userDB->update('updateuser', $updateData);
    $this->assertTrue($resultUsername);
    $selectedUsername = $this->userDB->select('updateuser');
    $this->assertEquals('Updated', $selectedUsername['first_name']);
    $this->assertEquals('Name', $selectedUsername['last_name']);

    // Test update by email
    $updateDataEmail = [
      'first_name' => 'EmailUpdated',
      'last_name' => 'EmailName',
    ];
    $resultEmail = $this->userDB->update('update@example.com', $updateDataEmail);
    $this->assertTrue($resultEmail);
    $selectedEmail = $this->userDB->select('update@example.com');
    $this->assertEquals('EmailUpdated', $selectedEmail['first_name']);
    $this->assertEquals('EmailName', $selectedEmail['last_name']);

    // Test update by id
    $userById = $this->userDB->select('updateuser');
    $updateDataId = [
      'first_name' => 'IdUpdated',
      'last_name' => 'IdName',
    ];
    $resultId = $this->userDB->update($userById['id'], $updateDataId);
    $this->assertTrue($resultId);
    $selectedId = $this->userDB->select($userById['id']);
    $this->assertEquals('IdUpdated', $selectedId['first_name']);
    $this->assertEquals('IdName', $selectedId['last_name']);

    // Test updating username itself
    $newUsername = 'updateduser';
    $updateUsernameData = [
      'username' => $newUsername
    ];
    $resultUsernameChange = $this->userDB->update($userById['id'], $updateUsernameData);
    $this->assertTrue($resultUsernameChange);
    $selectedNewUsername = $this->userDB->select($newUsername);
    $this->assertEquals($newUsername, $selectedNewUsername['username']);
  }

  public function testSaldoOperations(): void
  {
    $userData = [
      'username' => 'saldo',
      'password' => 'password123',
      'email' => 'saldo@example.com',
    ];
    $this->userDB->add($userData);
    $user = $this->userDB->select('saldo');
    $id = (int) $user['id'];
    $this->userDB->update_saldo($id, 100, 'refill_saldo');
    $saldo = $this->userDB->get_saldo($id);
    $this->assertEquals(100, $saldo);
    $this->userDB->update_saldo($id, -50, 'buy_package');
    $saldo = $this->userDB->get_saldo($id);
    $this->assertEquals(50, $saldo);
  }
}
