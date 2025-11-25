<?php

namespace PhpProxyHunter\Checker;

/**
 * Detect the proxy protocol by testing HTTP/HTTPS, SOCKS4, SOCKS4a, SOCKS5, SOCKS5h.
 *
 * @param string      $proxy    Proxy address in format "host:port"
 * @param int         $timeout  Socket timeout in seconds
 * @param string|null $username Optional username for authentication
 * @param string|null $password Optional password for authentication
 *
 * @return string One of:
 *                http, https, socks4, socks4a, socks5, socks5h, unknown
 */
function detectProxyProtocol($proxy, $timeout = 5, $username = null, $password = null) {
  $parts = explode(':', $proxy, 2);
  if (count($parts) !== 2) {
    return 'unknown';
  }

  $ip   = $parts[0];
  $port = (int) $parts[1];

  /**
   * --------------------------------------------------------
   * HTTP or HTTPS PROXY TEST
   * --------------------------------------------------------
   */
  $httpProto = detectHttpOrHttpsProxy($ip, $port, $timeout, $username, $password);
  if ($httpProto !== null) {
    return $httpProto;
    // 'http' or 'https'
  }

  /**
   * --------------------------------------------------------
   * SOCKS5 TEST (supports username/password)
   * --------------------------------------------------------
   */
  $fp = @fsockopen($ip, $port, $errno, $errstr, $timeout);
  if ($fp) {
    if ($username !== null && $password !== null) {
      // methods = 2 (no-auth + user/pass)
      @fwrite($fp, "\x05\x02\x00\x02");
    } else {
      // no-auth only
      @fwrite($fp, "\x05\x01\x00");
    }

    $resp = fread($fp, 2);

    if (strlen($resp) === 2 && $resp[0] === "\x05") {
      // ---- authentication step ----
      if ($resp[1] === "\x02") {
        if ($username === null || $password === null) {
          fclose($fp);
          goto SOCKS4_TEST;
        }

        $ulen = chr(strlen($username));
        $plen = chr(strlen($password));

        @fwrite($fp, "\x01" . $ulen . $username . $plen . $password);
        $authResp = fread($fp, 2);

        if (strlen($authResp) !== 2 || $authResp[1] !== "\x00") {
          fclose($fp);
          goto SOCKS4_TEST;
        }
      }

      if ($resp[1] !== "\x00" && $resp[1] !== "\x02") {
        fclose($fp);
        goto SOCKS4_TEST;
      }

      // ---- domain-connect test for socks5h ----
      $domain = 'example.com';
      $packet = "\x05\x01\x00\x03" . chr(strlen($domain)) . $domain . pack('n', 80);

      @fwrite($fp, $packet);
      $resp2 = fread($fp, 4);

      fclose($fp);

      if (!empty($resp2) && isset($resp2[1]) && $resp2[1] === "\x00") {
        return 'socks5h';
      }

      return 'socks5';
    }

    fclose($fp);
  }

  /**
   * --------------------------------------------------------
   * SOCKS4 TEST
   * --------------------------------------------------------
   */
  SOCKS4_TEST:

    $fp = @fsockopen($ip, $port, $errno, $errstr, $timeout);
  if ($fp) {
    $userid = ($username !== null ? $username : '');

    $packet = "\x04\x01" .                     // version, connect
                  pack('n', 80) .                 // port
                  inet_pton('93.184.216.34') .    // example.com IP
                  $userid . "\x00";

    @fwrite($fp, $packet);
    $resp = fread($fp, 2);
    fclose($fp);

    if (!empty($resp) && isset($resp[1]) && $resp[1] === "\x5A") {
      return 'socks4';
    }
  }

  /**
   * --------------------------------------------------------
   * SOCKS4A TEST
   * --------------------------------------------------------
   */
  $fp = @fsockopen($ip, $port, $errno, $errstr, $timeout);
  if ($fp) {
    $userid = ($username !== null ? $username : '');
    $domain = 'example.com';

    $packet = "\x04\x01" .
                  pack('n', 80) .
                  "\x00\x00\x00\x01" .
                  $userid . "\x00" .
                  $domain . "\x00";

    @fwrite($fp, $packet);

    $resp = fread($fp, 2);
    fclose($fp);

    if (!empty($resp) && isset($resp[1]) && $resp[1] === "\x5A") {
      return 'socks4a';
    }
  }

  return 'unknown';
}


/**
 * Detect HTTP or HTTPS (TLS-wrapped) proxy by testing CONNECT
 *
 * @param string      $ip
 * @param int         $port
 * @param int         $timeout
 * @param string|null $username
 * @param string|null $password
 *
 * @return string|null 'http', 'https', or null on failure
 */
function detectHttpOrHttpsProxy($ip, $port, $timeout, $username, $password) {
  $auth = '';
  if ($username !== null && $password !== null) {
    $auth = 'Proxy-Authorization: Basic ' .
                base64_encode($username . ':' . $password) . "\r\n";
  }

  $request = "CONNECT example.com:80 HTTP/1.1\r\n" .
        "Host: example.com\r\n" .
        $auth .
        "\r\n";

  // -------------------- HTTP --------------------
  $fp = @fsockopen($ip, $port, $errno, $errstr, $timeout);
  if ($fp) {
    $written = @fwrite($fp, $request);
    if ($written !== false) {
      $resp = fread($fp, 20);
      fclose($fp);

      if (strpos($resp, 'HTTP/1.1 200') === 0 || strpos($resp, 'HTTP/1.0 200') === 0) {
        return 'http';
      }
    } else {
      fclose($fp);
    }
  }

  // -------------------- HTTPS (TLS-wrapped) --------------------
  $context = stream_context_create([
    'ssl' => [
      'verify_peer'       => false,
      'verify_peer_name'  => false,
      'allow_self_signed' => true,
    ],
  ]);

  $fp = @stream_socket_client(
    "tls://{$ip}:{$port}",
    $errno,
    $errstr,
    $timeout,
    STREAM_CLIENT_CONNECT,
    $context
  );

  if ($fp) {
    $written = @fwrite($fp, $request);
    if ($written !== false) {
      $resp = fread($fp, 20);
      fclose($fp);

      if (strpos($resp, 'HTTP/1.1 200') === 0 || strpos($resp, 'HTTP/1.0 200') === 0) {
        return 'https';
      }
    } else {
      fclose($fp);
    }
  }

  return null;
}


class ProxyTypeDetector {
  /**
   * Detect the proxy type (protocol).
   *
   * @param string      $proxy    Proxy address in format "host:port"
   * @param int         $timeout  Socket timeout in seconds. Default: 5
   * @param string|null $username Optional username for authentication
   * @param string|null $password Optional password for authentication
   *
   * @return array Detection result with keys:
   *               - 'result': string One of: http, https, socks4, socks4a, socks5, socks5h, unknown
   *               - 'port-open': bool|null True if port is open, false if closed, null if unable to determine
   *               - 'proxy-valid': bool True if proxy format is valid, false otherwise
   */
  public static function detect($proxy, $timeout = 5, $username = null, $password = null) {
    $extract = extractProxies($proxy);
    $proxy   = $extract[0]->proxy;

    $isValid      = isValidProxy($proxy, true);
    $portOpen     = $isValid ? isPortOpen($proxy, $timeout) : false;
    $protocolType = ($isValid && $portOpen) ? detectProxyProtocol($proxy, $timeout, $username, $password) : 'unknown';

    return [
      'result'      => $protocolType,
      'port-open'   => $portOpen,
      'proxy-valid' => $isValid,
    ];
  }
}
