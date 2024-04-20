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

require_once __DIR__ . '/func.php';

// Allow from any origin
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");
header('Content-Type: text/plain; charset=utf-8');

$filePath = __DIR__ . '/proxies.txt';

// Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
  if (isset($_POST['proxies'])) {
    $ip_port = $_POST['proxies'];

    // Extract IP:PORT pairs into an array
    preg_match_all('/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}:\d+)/', $ip_port, $matches);
    $ip_port_array = $matches[0];

    // Write IP:PORT pairs into proxy.txt file in append mode
    $file = fopen($filePath, 'a');
    foreach (array_unique($ip_port_array) as $ip_port) {
      if ($ip_port) fwrite($file, $ip_port . PHP_EOL);
    }
    fclose($file);

    $write = rewriteIpPortFile($filePath);

    // check lock files
    $locks = glob(__DIR__ . '/*.lock');

    if (count($locks) > 0) {
      // lock exist, backup added proxies
      file_put_contents(__DIR__ . '/proxies-backup.txt', PHP_EOL . implode(PHP_EOL, array_unique($ip_port_array)) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    $total = count($write);
    echo "IP:PORT pairs ($total) written to proxies.txt successfully.";
  } else {
    echo "IP:PORT data not found in POST request.";
  }
}
