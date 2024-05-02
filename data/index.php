<?php

require_once __DIR__ . '/../func.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

header('Content-Type: application/json');
// header('Content-Type: text/plain');

if (isset($_REQUEST['uid'])) {
  $uid = urldecode(trim($_REQUEST['uid']));
  $user_file = realpath(__DIR__ . "/$uid.json");
  if (isset($_SESSION['admin']) && $_SESSION['admin'] === true) {
    if (isset($_REQUEST['create'])) {
      $currentDate = new DateTime();
      $currentDate->add(new DateInterval('P1W')); // Adding 1 week

      // Format the date according to RFC3339
      $oneWeekLater = $currentDate->format(DateTime::RFC3339); // date(DATE_RFC3339)

      file_put_contents($user_file, json_encode(['valid_until' => $oneWeekLater]));
    }
  }
  if ($user_file !== false && file_exists($user_file)) {
    $data_str = file_get_contents($user_file);
    $data = json_decode($data_str, true);
    exit($data_str);
  }
}
