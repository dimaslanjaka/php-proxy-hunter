<?php /** @noinspection PhpFullyQualifiedNameUsageInspection */

require_once __DIR__ . '/func-proxy.php';

$isCli = (php_sapi_name() === 'cli' || defined('STDIN') || (empty($_SERVER['REMOTE_ADDR']) && !isset($_SERVER['HTTP_USER_AGENT']) && count($_SERVER['argv']) > 0));

if (!$isCli) header('Content-Type:text/plain; charset=UTF-8');
if (!$isCli)
  exit('web server access disallowed');

$db = new \PhpProxyHunter\ProxyDB();
$db->getDeadProxies();

/**
 * Proxy server checker for proxyscan.php
 */
function is_proxy(string $proxy_server)
{
  $proxyDict = array(
      "http" => $proxy_server,
      "https" => $proxy_server,
      "socks" => $proxy_server
  );

  $test_site = "http://api.ipify.org/?format=json";
  $headers = array('user-agent' => 'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US; rv:1.9.1.5) Gecko/20091102 Firefox/3.5.5 (.NET CLR 3.5.30729)');

  foreach ($proxyDict as $type => $proxy) {
    try {
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $test_site);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_PROXY, $proxy);
      curl_setopt($ch, CURLOPT_PROXYTYPE, $type === 'socks' ? CURLPROXY_SOCKS5 : CURLPROXY_HTTP);
      if ($type === "socks") {
        curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
      }
      curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
      $response = curl_exec($ch);
      $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);
      if ($status === 200) {
        return $type;
      }
    } catch (Exception $e) {
      return false;
    }
  }
  return false;
}
