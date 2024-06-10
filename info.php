<?php

require_once __DIR__ . '/func-proxy.php';

global $isCli;

if (!$isCli) {
  header('Content-Type: application/json; charset=utf-8');

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
$config['admin'] = isset($_SESSION['admin']) && $_SESSION['admin'] === true;
$config['pid'] = $_ENV['CPID'];
$config['captcha'] = isset($_SESSION['captcha']) && $_SESSION['captcha'];
$config['captcha-site-key'] = $_ENV['G_RECAPTCHA_SITE_KEY'];
$config['server-ip'] = getServerIp();
$config_json = json_encode($config);

if (!$isCli) set_cookie("user_config", base64_encode($config_json));

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
