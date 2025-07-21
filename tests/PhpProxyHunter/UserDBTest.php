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

  protected function setUp(): void
  {
    $this->testDbPath = tmp() . '/test_database.sqlite';
    // Remove any existing test DB
    if (file_exists($this->testDbPath)) {
      // Try to close any open connection before unlink
      @unlink($this->testDbPath);
    }
    $this->userDB = new UserDB($this->testDbPath);
  }

  protected function tearDown(): void
  {
    // Close DB connection before deleting file
    $this->userDB->close();
    gc_collect_cycles();
    if (file_exists($this->testDbPath)) {
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
    $this->userDB->update('updateuser', $updateData);
    $selected = $this->userDB->select('updateuser');
    $this->assertEquals('Updated', $selected['first_name']);
    $this->assertEquals('Name', $selected['last_name']);
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
