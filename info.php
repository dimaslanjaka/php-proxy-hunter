<?php

require_once __DIR__ . '/func.php';

// Allow from any origin
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");
header('Content-Type: application/json; charset=utf-8');

set_cookie("user_id", getUserId());
$config = getConfig(getUserId());
// admin info from 'data/login.php'
$config['admin'] = isset($_SESSION['admin']) && $_SESSION['admin'] == true;
$config_json = json_encode($config);

set_cookie("user_config", base64_encode($config_json));

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
