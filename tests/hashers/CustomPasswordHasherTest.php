<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/hashers/CustomPasswordHasher.php';

class CustomPasswordHasherTest extends TestCase
{
  private string $password;

  protected function setUp(): void
  {
    $this->password = 'my_secure_password';
  }

  public function testInvalidEncodedFormats(): void
  {
    $this->assertFalse(CustomPasswordHasher::verify('irrelevant', ''));
    $this->assertFalse(CustomPasswordHasher::verify('irrelevant', 'notavalidformat'));
    $this->assertFalse(CustomPasswordHasher::verify('irrelevant', '$somesalt'));
    $this->assertFalse(CustomPasswordHasher::verify('irrelevant', 'somehash$'));
  }

  public function testGenerateSalt(): void
  {
    $salt = CustomPasswordHasher::getSalt();
    $this->assertIsString($salt);
    $this->assertNotEmpty($salt);
  }

  public function testHashAndVerify(): void
  {
    $encoded = CustomPasswordHasher::hash($this->password);
    $this->assertIsString($encoded);
    $this->assertNotEmpty($encoded);
    $isPhpValid = CustomPasswordHasher::verify($this->password, $encoded);
    $this->assertTrue($isPhpValid);
  }

  public function testVerifyPythonHash(): void
  {
    $from_py = 'd2db5f1a1c8658d87a0e696ca24bd86fba0c1ed43646a55649b714b8b9ff6b0c$558b06a41620e188';
    $isValid = CustomPasswordHasher::verify($this->password, $from_py);
    $this->assertTrue($isValid);
  }
}
