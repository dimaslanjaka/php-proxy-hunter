<?php

require_once __DIR__ . '/func-proxy.php';

use PhpProxyHunter\Server;

$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

global $isCli, $isAdmin;

if (!$isCli) {
  // Set response content header json
  header('Content-Type: application/json; charset=utf-8');

  // Set the Cache-Control header to cache the response for 1 hour (3600 seconds)
  header("Cache-Control: max-age=3600, must-revalidate");

  // Optionally, set the Expires header to a timestamp 1 hour in the future
  header("Expires: " . gmdate("D, d M Y H:i:s", time() + 3600) . " GMT");

  // modify config
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = parsePostData();
    if (isset($input['config'])) {
      $set = setConfig(getUserId(), $input['config']);
    }
  }

  if (isset($_REQUEST['txt'])) {
    header('Content-Type: text/plain; charset=utf-8');
  }

  // get config
  set_cookie("user_id", getUserId());
}

$config = getConfig(getUserId());
// admin info from 'data/login.php'
$config['admin'] = $isAdmin; // isset($_SESSION['admin']) && $_SESSION['admin'] === true;
$config['pid'] = $_ENV['CPID'];
$config['captcha'] = isset($_SESSION['captcha']) && $_SESSION['captcha'];
$config['captcha-site-key'] = $_ENV['G_RECAPTCHA_SITE_KEY'];
$config['captcha-v2-site-key'] = $_ENV['G_RECAPTCHA_V2_SITE_KEY'];
$config['server-ip'] = getServerIp();
$config['your-ip'] = Server::getRequestIP();
$config['your-useragent'] = Server::useragent();
$config['your-hash'] = getUserId();
$config_json = json_encode($config);

if (!$isCli) {
  set_cookie("user_config", base64_encode($config_json));
}

echo $config_json;

function set_cookie($name, $value, $expiration_days = 1, $path = "/", $domain = null)
{
  // Calculate the expiration time (in seconds)
  $expiration_time = time() + ($expiration_days * 24 * 60 * 60);

  // Set the domain to current domain if not provided
  if ($domain === null) {
    $domain = $_SERVER['HTTP_HOST'];
  }

  // Set the cookie
  setcookie($name, $value, $expiration_time, $path, $domain);
}

function listProcesses()
{
  $phpProcesses = [];
  $pythonProcesses = [];
  $processes = [];
  $cmd = '';

  if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    // Windows
    exec("tasklist /fi \"imagename eq php.exe\"", $phpProcesses);
    exec("tasklist /fi \"imagename eq python.exe\"", $pythonProcesses);
  } else {
    // Linux
    exec("ps aux | grep '[p]hp'", $phpProcesses);
    exec("ps aux | grep '[p]ython'", $pythonProcesses);
  }

  // Collect PHP processes
  foreach ($phpProcesses as $process) {
    $processes['php'][] = $process;
  }

  // Collect Python processes
  foreach ($pythonProcesses as $process) {
    $processes['python'][] = $process;
  }

  return $processes;
}
