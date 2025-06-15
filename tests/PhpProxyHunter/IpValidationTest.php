<?php

use PHPUnit\Framework\TestCase;

class IpValidationTest extends TestCase
{
  public function testValidIPv4WithPort()
  {
    $this->assertTrue(isValidIp('192.168.1.1:8080'));
  }

  public function testValidIPv4WithoutPort()
  {
    $this->assertTrue(isValidIp('10.0.0.1'));
  }

  public function testInvalidIpWithDoubleDots()
  {
    $this->assertFalse(isValidIp('192..168.1.1:8080'));
  }

  public function testInvalidShortIp()
  {
    $this->assertFalse(isValidIp('1.1.1:80'));
  }

  public function testCompletelyInvalidString()
  {
    $this->assertFalse(isValidIp('not_an_ip'));
  }

  public function testEmptyString()
  {
    $this->assertFalse(isValidIp(''));
  }

  public function testNullInput()
  {
    $this->assertFalse(isValidIp(null));
  }

  public function testLoopbackAddress()
  {
    $this->assertTrue(isValidIp('127.0.0.1:3128'));
  }

  public function testZeroStartingIp()
  {
    $this->assertFalse(isValidIp('0.0.0.0:1234')); // regex (?!0) fails here
  }

  public function testValidIpWithLeadingZeros()
  {
    $this->assertFalse(isValidIp('01.02.03.04:1234')); // Still valid as per filter_var
  }

  public function testOthers()
  {
    $this->assertFalse(isValidIp('256.256.256.256')); // Invalid IP
    $this->assertFalse(isValidIp('0')); // Invalid IP
  }
}
