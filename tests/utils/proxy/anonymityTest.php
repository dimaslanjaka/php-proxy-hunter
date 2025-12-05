<?php

use PHPUnit\Framework\TestCase;

class AnonymityTest extends TestCase {
  public function testTransparentWhenIpInfoReportsRealIp() {
    $ipInfos = [
      ['content' => 'Your IP is 123.123.123.123'],
    ];

    $judgeInfos = [
      ['content' => ''],
    ];

    $result = parse_anonymity($ipInfos, $judgeInfos, '123.123.123.123');

    $this->assertSame('Transparent', $result);
  }

  public function testTransparentWhenJudgeHeadersLeakRealIp() {
    $ipInfos = [
      ['content' => 'Proxy IP is 99.99.99.99'],
    ];

    $judgeInfos = [
      ['content' => 'X-Forwarded-For: 123.123.123.123'],
    ];

    $result = parse_anonymity($ipInfos, $judgeInfos, '123.123.123.123');

    $this->assertSame('Transparent', $result);
  }

  public function testAnonymousWhenProxyHeadersAppear() {
    $ipInfos = [
      ['content' => 'Proxy IP 99.99.99.99'],
    ];

    $judgeInfos = [
      ['content' => 'X-Forwarded-For: 99.99.99.99'],
    ];

    $result = parse_anonymity($ipInfos, $judgeInfos, '123.123.123.123');

    $this->assertSame('Anonymous', $result);
  }

  public function testAnonymousWhenXForwardedForPresentButRealIpNotShown() {
    $ipInfos = [
      ['content' => 'Proxy IP 99.99.99.99'],
    ];

    $judgeInfos = [
      ['content' => 'X-Forwarded-For: 88.88.88.88'],
    ];

    $result = parse_anonymity($ipInfos, $judgeInfos, '123.123.123.123');

    $this->assertSame('Anonymous', $result);
  }

  public function testEliteWhenProxyIpOnlyAndNoProxyHeaders() {
    $ipInfos = [
      ['content' => '99.99.99.99'],
    ];

    $judgeInfos = [
      ['content' => ''],
    ];

    $result = parse_anonymity($ipInfos, $judgeInfos, '123.123.123.123');

    $this->assertSame('Elite', $result);
  }

  public function testEliteJsonResponseWithNoLeaks() {
    $ipInfos = [
      ['content' => '{"ip":"99.99.99.99"}'],
    ];

    $judgeInfos = [
      ['content' => '{"headers":{"Host":"example.com","User-Agent":"Test"}}'],
    ];

    $result = parse_anonymity($ipInfos, $judgeInfos, '123.123.123.123');

    $this->assertSame('Elite', $result);
  }

  public function testExtractIpsFromText() {
    $text = 'Your IPs are 1.2.3.4 and 2001:db8::1';

    $ips = extract_ips_from_text($text);

    $this->assertContains('1.2.3.4', $ips);
    $this->assertContains('2001:db8::1', $ips);
  }

  public function testParseHeadersRaw() {
    $raw = "X-Test: value\nX-Forwarded-For: 1.2.3.4";

    $headers = parse_headers_from_judge($raw);

    $this->assertArrayHasKey('X-Test', $headers);
    $this->assertArrayHasKey('X-Forwarded-For', $headers);
  }

  public function testParseHeadersJson() {
    $json = '{"headers":{"Via":"1.1 proxy","Host":"test"}}';

    $headers = parse_headers_from_judge($json);

    $this->assertArrayHasKey('Via', $headers);
    $this->assertArrayHasKey('Host', $headers);
  }

  public function testNotWorkingReturnsEmpty() {
    $ipInfos = [
      ['content' => ''],
    ];

    $judgeInfos = [
      ['content' => ''],
    ];

    $result = parse_anonymity($ipInfos, $judgeInfos, '123.123.123.123');

    $this->assertSame('', $result);
  }
}
