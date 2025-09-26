<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../src/utils/proxy/extractor.php';

class ExtractProxiesTest extends TestCase
{
  public function testExtractProxiesJsonString()
  {
    $input  = '{"ip":"192.168.1.1","port":"8080"}';
    $result = extractProxies($input, null, false);
    $this->assertEquals('192.168.1.1:8080', $result[0]->proxy);
    $this->assertEmpty($result[0]->username ?? '');
    $this->assertEmpty($result[0]->password ?? '');
  }

  public function testExtractProxiesUserPassAtIpPort()
  {
    $input  = 'user:pass@89.32.45.23:8080';
    $result = extractProxies($input, null, false);
    $this->assertNotEmpty($result);
    $this->assertEquals('89.32.45.23:8080', $result[0]->proxy);
    $this->assertEquals('user', $result[0]->username);
    $this->assertEquals('pass', $result[0]->password);
  }

  public function testExtractProxiesIpPortAtUserPass()
  {
    $input  = '78.43.25.89:8000@user:pass';
    $result = extractProxies($input, null, false);
    $this->assertNotEmpty($result);
    $this->assertEquals('78.43.25.89:8000', $result[0]->proxy);
    $this->assertEquals('user', $result[0]->username);
    $this->assertEquals('pass', $result[0]->password);
  }

  public function testExtractProxiesPlainIpPort()
  {
    $input  = '90.0.0.1:1080';
    $result = extractProxies($input, null, false);
    $this->assertNotEmpty($result);
    $this->assertEquals('90.0.0.1:1080', $result[0]->proxy);
    $this->assertEmpty($result[0]->username ?? '');
    $this->assertEmpty($result[0]->password ?? '');
  }

  public function testExtractProxiesFromLongText()
  {
    $input  = 'Lorem ipsum dolor sit amet, user:pass@89.32.45.23:8080 consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.';
    $result = extractProxies($input, null, false);
    $this->assertNotEmpty($result);
    $this->assertEquals('89.32.45.23:8080', $result[0]->proxy);
    $this->assertEquals('user', $result[0]->username);
    $this->assertEquals('pass', $result[0]->password);
  }
}
