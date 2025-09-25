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
  Script Name: Random User-Agent Generator
*/

require_once __DIR__ . '/func.php';

$isCli = (php_sapi_name() === 'cli' || defined('STDIN') || (empty($_SERVER['REMOTE_ADDR']) && !isset($_SERVER['HTTP_USER_AGENT']) && count($_SERVER['argv']) > 0));

if (!$isCli) {
  // Allow from any origin
  header('Access-Control-Allow-Origin: *');
  header('Access-Control-Allow-Headers: *');
  header('Access-Control-Allow-Methods: *');
  header('Content-Type: text/plain; charset=utf-8');
}

$max = 1;
if (isset($_REQUEST['max'])) {
  $max = intval($_REQUEST['max']);
}
// limit max 50
if ($max > 50) {
  $max = 50;
}
$type = 'random';
// browser type [firefox, chrome]
if (isset($_REQUEST['type'])) {
  $type = $_REQUEST['type'];
}
$os = 'random';
if (isset($_REQUEST['os'])) {
  $os = $_REQUEST['os'];
}

for ($i = 0; $i < $max; $i++) {
  if ($type == 'random') {
    $uaType = getRandomItemFromArray(['chrome', 'firefox']);
  } else {
    $uaType = $type;
  }
  $android = randomAndroidUa($uaType);
  $ios     = randomIosUa($uaType);
  if ($os == 'random') {
    echo getRandomItemFromArray([$ios, $android]) . PHP_EOL;
  } elseif ($os == 'android') {
    echo $android . PHP_EOL;
  } elseif ($os == 'ios') {
    echo $ios . PHP_EOL;
  }
}

// http://dev.webmanajemen.com/useragent.php?type=[chrome,firefox]&max=[n]&os=[android,ios]
