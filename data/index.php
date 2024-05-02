<?php

header('Content-Type: application/json');

if (isset($_REQUEST['uid'])) {
  $uid = urldecode(trim($_REQUEST['uid']));
  $user_file = realpath(__DIR__ . "/$uid.json");
  if ($user_file !== false && file_exists($user_file)) {
    $data_str = file_get_contents($user_file);
    $data = json_decode($data_str, true);
    echo $data_str;
  }
}
