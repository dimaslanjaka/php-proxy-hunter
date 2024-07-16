<?php

/*
  ----------------------------------------------------------------------------
  LICENSE
  ----------------------------------------------------------------------------
  This file is part of Proxy Checker.

  Proxy Checker is free software: you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.

  Proxy Checker is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with Proxy Checker.  If not, see <https://www.gnu.org/licenses/>.

  ----------------------------------------------------------------------------
  Copyright (c) 2024 Dimas lanjaka
  ----------------------------------------------------------------------------
  This project is licensed under the GNU General Public License v3.0
  For full license details, please visit: https://www.gnu.org/licenses/gpl-3.0.html

  If you have any inquiries regarding the license or permissions, please contact:

  Name: Dimas Lanjaka
  Website: https://www.webmanajemen.com
  Email: dimaslanjaka@gmail.com
*/

require_once __DIR__ . '/func-proxy.php';

$isCli = (php_sapi_name() === 'cli' || defined('STDIN') || (empty($_SERVER['REMOTE_ADDR']) && !isset($_SERVER['HTTP_USER_AGENT']) && count($_SERVER['argv']) > 0));
$strings = '';

if (!$isCli) {
  // Allow from any origin
  header("Access-Control-Allow-Origin: *");
  header("Access-Control-Allow-Headers: *");
  header("Access-Control-Allow-Methods: *");
  header('Content-Type: text/plain; charset=utf-8');
  // Check if the form was submitted
  if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['proxies'])) {
      $strings = rawurldecode($_POST['proxies']);
      if (isBase64Encoded($strings)) {
        $strings = base64_decode($strings);
      }
    }
  }
}

$proxies = extractProxies($strings);
$proxies = array_filter($proxies, function (\PhpProxyHunter\Proxy $item) {
  if (empty($item->status)) {
    return true;
  }
  if (empty($item->last_check)) {
    return true;
  }
  return isDateRFC3339OlderThanHours($item->last_check, 5);
});
$proxies_txt_array = array_map(function (\PhpProxyHunter\Proxy $item) {
  $raw_proxy = $item->proxy;
  if (!empty($item->username) && !empty($item->password)) {
    $raw_proxy .= "@" . $item->username . ":" . $item->password;
  }
  return $raw_proxy;
}, $proxies);
$proxies_txt = implode(PHP_EOL, $proxies_txt_array);

$filePath = __DIR__ . '/proxies.txt';
// write proxies into proxies.txt or proxies-backup.txt when checker still running
if (file_exists(__DIR__ . '/proxyChecker.lock')) {
  // lock exist, backup added proxies
  if (!$isCli) {
    $id = sanitizeFilename(\PhpProxyHunter\Server::useragent() . '-' . \PhpProxyHunter\Server::getRequestIP());
  } else {
    $id = 'CLI';
  }
  $output = __DIR__ . '/assets/proxies/added-' . $id . '.txt';
  append_content_with_lock($output, PHP_EOL . $proxies_txt);
  setMultiPermissions($output);
} else {
  append_content_with_lock(__DIR__ . '/proxies.txt', PHP_EOL . $proxies_txt);
}

$count = count($proxies);
if ($count > 0) {
  echo $count . " proxies added successfully." . PHP_EOL;
} else {
  echo "Proxy added successfully." . PHP_EOL;
}
