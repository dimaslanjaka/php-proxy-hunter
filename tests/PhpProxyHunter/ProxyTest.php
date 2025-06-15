<?php

use PhpProxyHunter\Proxy;
use PHPUnit\Framework\TestCase;

class ProxyTest extends TestCase
{
  public function testCanCreateProxyWithAllFields()
  {
    $proxy = new Proxy(
      '123.123.123.123:8080',
      '100ms',
      'HTTP',
      'Asia',
      'Jakarta',
      'Indonesia',
      '2025-06-14 12:00:00',
      'high',
      'active',
      'Asia/Jakarta',
      '106.8456',
      'yes',
      '-6.2088',
      'id',
      'Mozilla/5.0',
      'VendorX',
      'RendererY',
      'Google Inc.',
      'user1',
      'pass123',
      1,
      'true'
    );

    $this->assertSame('123.123.123.123:8080', $proxy->proxy);
    $this->assertSame('100ms', $proxy->latency);
    $this->assertSame('HTTP', $proxy->type);
    $this->assertSame('Asia', $proxy->region);
    $this->assertSame('Jakarta', $proxy->city);
    $this->assertSame('Indonesia', $proxy->country);
    $this->assertSame('2025-06-14 12:00:00', $proxy->last_check);
    $this->assertSame('high', $proxy->anonymity);
    $this->assertSame('active', $proxy->status);
    $this->assertSame('Asia/Jakarta', $proxy->timezone);
    $this->assertSame('106.8456', $proxy->longitude);
    $this->assertSame('yes', $proxy->private);
    $this->assertSame('-6.2088', $proxy->latitude);
    $this->assertSame('id', $proxy->lang);
    $this->assertSame('Mozilla/5.0', $proxy->useragent);
    $this->assertSame('VendorX', $proxy->webgl_vendor);
    $this->assertSame('RendererY', $proxy->webgl_renderer);
    $this->assertSame('Google Inc.', $proxy->browser_vendor);
    $this->assertSame('user1', $proxy->username);
    $this->assertSame('pass123', $proxy->password);
    $this->assertSame(1, $proxy->id);
    $this->assertSame('true', $proxy->https);
  }

  public function testCanCreateProxyWithOnlyRequiredField()
  {
    $proxy = new Proxy('123.123.123.123:8080');

    $this->assertSame('123.123.123.123:8080', $proxy->proxy);
    $this->assertNull($proxy->latency);
    $this->assertNull($proxy->type);
    $this->assertNull($proxy->region);
    $this->assertNull($proxy->city);
    $this->assertNull($proxy->country);
    $this->assertNull($proxy->last_check);
    $this->assertNull($proxy->anonymity);
    $this->assertNull($proxy->status);
    $this->assertNull($proxy->timezone);
    $this->assertNull($proxy->longitude);
    $this->assertNull($proxy->private);
    $this->assertNull($proxy->latitude);
    $this->assertNull($proxy->lang);
    $this->assertNull($proxy->useragent);
    $this->assertNull($proxy->webgl_vendor);
    $this->assertNull($proxy->webgl_renderer);
    $this->assertNull($proxy->browser_vendor);
    $this->assertNull($proxy->username);
    $this->assertNull($proxy->password);
    $this->assertNull($proxy->id);
    $this->assertSame('false', $proxy->https);
  }
}
