<?php

require_once __DIR__ . '/../bootstrap.php';

use PhpProxyHunter\CoreDB;

$mysqlHost = $_ENV['MYSQL_HOST'] ?? getenv('MYSQL_HOST');
$mysqlUser = $_ENV['MYSQL_USER'] ?? getenv('MYSQL_USER');
$mysqlPass = $_ENV['MYSQL_PASS'] ?? getenv('MYSQL_PASS');
$mysqlDb   = 'phpunit_test_db';
$db        = new CoreDB(null, $mysqlHost, $mysqlDb, $mysqlUser, $mysqlPass, false);
$log_db    = $db->log_db;
$user_db   = $db->user_db;

$adminEmails = getAdminEmails();
foreach ($adminEmails as $email) {
  $user = $user_db->select($email);
  // $user_db->select may return a single user array or an array of users; handle both
  if (is_array($user)) {
    if (isset($user['id'])) {
      $user_id = (int) $user['id'];
    } elseif (isset($user[0]['id'])) {
      $user_id = (int) $user[0]['id'];
    } else {
      continue;
    }
    // Populate with sample actions for each admin
    // Only use action_type values defined in ActivityLog::MYSQL_SCHEMA
    $log_db->log($user_id, 'LOGIN', null, null, null, ['info' => 'Admin login for populate test']);
    usleep(200000);
    $log_db->log($user_id, 'PACKAGE_CREATE', 101, 'package', null, ['package_name' => 'Test Package', 'points' => 100]);
    usleep(200000);
    $log_db->log($user_id, 'PACKAGE_UPDATE', 102, 'package', null, ['package_name' => 'Test Package Updated', 'points' => 150]);
    usleep(200000);
    $log_db->log($user_id, 'PACKAGE_DELETE', 103, 'package', null, ['package_name' => 'Test Package Deleted']);
    usleep(200000);
    $log_db->log($user_id, 'PACKAGE_BUY', 104, 'package', null, ['package_name' => 'Test Package Bought', 'points' => 200]);
    usleep(200000);
    $log_db->log($user_id, 'TOPUP', 201, 'topup', null, ['amount' => 50, 'method' => 'admin']);
  }
}
