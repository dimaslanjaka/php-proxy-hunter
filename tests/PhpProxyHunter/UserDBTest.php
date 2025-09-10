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
  private ?UserDB $userDB     = null;
  private ?string $testDbPath = null;

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
    $this->mysqlDb   = 'php_proxy_hunter_test';
  }

  protected function setUpDB(string $driver): void
  {
    if ($driver === 'mysql') {
      $this->userDB = new UserDB(
        null,
        'mysql',
        $this->mysqlHost,
        $this->mysqlDb,
        $this->mysqlUser,
        $this->mysqlPass,
        true
      );
      // Remove related user_discount rows before deleting test users
      $pdo     = $this->userDB->db->pdo;
      $userIds = $pdo->query("SELECT id FROM auth_user WHERE username IN ('testuser', 'updateuser', 'saldo') OR email IN ('test@example.com', 'update@example.com', 'saldo@example.com')")->fetchAll(PDO::FETCH_COLUMN);
      if ($userIds && count($userIds) > 0) {
        $ids = implode(',', array_map('intval', $userIds));
        $pdo->exec("DELETE FROM user_discount WHERE user_id IN ($ids)");
        $pdo->exec("DELETE FROM user_logs WHERE user_id IN ($ids)");
        $pdo->exec("DELETE FROM user_fields WHERE user_id IN ($ids)");
        $pdo->exec("DELETE FROM auth_user WHERE id IN ($ids)");
      }
    } else {
      $this->testDbPath = sys_get_temp_dir() . '/test_database.sqlite';
      $this->userDB     = new UserDB($this->testDbPath);
      // Remove related user_discount rows before deleting test users
      $pdo     = $this->userDB->db->pdo;
      $userIds = $pdo->query("SELECT id FROM auth_user WHERE username IN ('testuser', 'updateuser', 'saldo') OR email IN ('test@example.com', 'update@example.com', 'saldo@example.com')")->fetchAll(PDO::FETCH_COLUMN);
      if ($userIds && count($userIds) > 0) {
        $ids = implode(',', array_map('intval', $userIds));
        $pdo->exec("DELETE FROM user_discount WHERE user_id IN ($ids)");
      }
      $pdo->exec("DELETE FROM auth_user WHERE username IN ('testuser', 'updateuser', 'saldo') OR email IN ('test@example.com', 'update@example.com', 'saldo@example.com')");
    }
  }

  protected function tearDownDB(string $driver): void
  {
    if ($this->userDB) {
      $this->userDB->close();
      $this->userDB = null;
    }
    gc_collect_cycles();
    if ($driver === 'sqlite' && $this->testDbPath && file_exists($this->testDbPath)) {
      @unlink($this->testDbPath);
    }
  }

  /**
   * @dataProvider dbProvider
   */
  public function testAddAndSelectUser(string $driver): void
  {
    $this->setUpDB($driver);

    $userData = [
      'username'   => 'testuser',
      'password'   => 'password123',
      'email'      => 'test@example.com',
      'first_name' => 'Test',
      'last_name'  => 'User',
    ];
    $result = $this->userDB->add($userData);
    $this->assertTrue($result);

    $selected = $this->userDB->select('testuser');
    $this->assertNotEmpty($selected);
    $this->assertEquals('testuser', $selected['username']);
    $this->assertEquals('test@example.com', $selected['email']);

    $this->tearDownDB($driver);
  }

  /**
   * @dataProvider dbProvider
   */
  public function testUpdateUser(string $driver): void
  {
    $this->setUpDB($driver);

    $userData = [
      'username' => 'updateuser',
      'password' => 'password123',
      'email'    => 'update@example.com',
    ];
    $this->userDB->add($userData);

    $updateData = ['first_name' => 'Updated', 'last_name' => 'Name'];
    $this->assertTrue($this->userDB->update('updateuser', $updateData));
    $selected = $this->userDB->select('updateuser');
    $this->assertEquals('Updated', $selected['first_name']);
    $this->assertEquals('Name', $selected['last_name']);

    $updateDataEmail = ['first_name' => 'EmailUpdated', 'last_name' => 'EmailName'];
    $this->assertTrue($this->userDB->update('update@example.com', $updateDataEmail));
    $selected = $this->userDB->select('update@example.com');
    $this->assertEquals('EmailUpdated', $selected['first_name']);
    $this->assertEquals('EmailName', $selected['last_name']);

    $userById     = $this->userDB->select('updateuser');
    $updateDataId = ['first_name' => 'IdUpdated', 'last_name' => 'IdName'];
    $this->assertTrue($this->userDB->update($userById['id'], $updateDataId));
    $selected = $this->userDB->select($userById['id']);
    $this->assertEquals('IdUpdated', $selected['first_name']);
    $this->assertEquals('IdName', $selected['last_name']);

    $newUsername = 'updateduser';
    $this->assertTrue($this->userDB->update($userById['id'], ['username' => $newUsername]));
    $selected = $this->userDB->select($newUsername);
    $this->assertEquals($newUsername, $selected['username']);

    $this->tearDownDB($driver);
  }

  /**
   * @dataProvider dbProvider
   */
  public function testSaldoOperations(string $driver): void
  {
    $this->setUpDB($driver);

    $userData = [
      'username' => 'saldo',
      'password' => 'password123',
      'email'    => 'saldo@example.com',
    ];
    $this->userDB->add($userData);
    $user = $this->userDB->select('saldo');
    $id   = (int) $user['id'];

    $this->userDB->updatePoint($id, 100, 'refill_saldo', '', false);
    $this->assertEquals(100, $this->userDB->getPoint($id));

    $this->userDB->updatePoint($id, -50, 'buy_package', '', false);
    $this->assertEquals(50, $this->userDB->getPoint($id));

    $this->userDB->updatePoint($id, 777, 'admin_set', '', true);
    $this->assertEquals(777, $this->userDB->getPoint($id));

    $this->tearDownDB($driver);
  }

  public function deleteUser($driver)
  {
    $this->setUpDB($driver);

    // Add test user to ensure they exist before deletion
    $user = [
      'username' => 'testuser009',
      'password' => 'password123',
      'email'    => 'test009@example.com',
    ];
    $this->userDB->add($user);

    // Check user exists
    $user = $this->userDB->select('testuser009');
    $this->assertNotEmpty($user);

    // Delete user
    $result = $this->userDB->delete('testuser009');
    $this->assertTrue($result);
    $user = $this->userDB->select('testuser009');
    $this->assertEmpty($user);

    $this->tearDownDB($driver);
  }
}
