<?php

// remove duplicate IP from database

require __DIR__ . '/func-proxy.php';

global $isCli;

use PhpProxyHunter\ProxyDB;

if (!$isCli) {
  exit('only CLI allowed');
}

$isAdmin = false; // admin indicator
$max_checks = 500; // max proxies to be checked
$maxExecutionTime = 120; // max 120s execution time

if ($isCli) {
  $short_opts = "p:m::";
  $long_opts = [
    "proxy:",
    "max::",
    "userId::",
    "lockFile::",
    "runner::",
    "admin::"
  ];
  $options = getopt($short_opts, $long_opts);
  if (!empty($options['max'])) {
    $max = intval($options['max']);
    if ($max > 0) {
      $max_checks = $max;
    }
  }
  if (!empty($options['admin']) && $options['admin'] !== 'false') {
    $isAdmin = true;
    // set time limit 10 minutes for admin
    $maxExecutionTime = 10 * 60;
    // disable execution limit
    set_time_limit(0);
  }
}

$lockFilePath = __DIR__ . "/proxyChecker.lock";
$statusFile = __DIR__ . "/status.txt";

if (file_exists($lockFilePath) && !is_debug()) {
  exit(date(DATE_RFC3339) . ' another process still running' . PHP_EOL);
} else {
  file_put_contents($lockFilePath, date(DATE_RFC3339));
  file_put_contents($statusFile, 'filter-ports');
}

$db = new ProxyDB();
$pdo = $db->db->pdo;

// Step 1: Identify and process duplicates based on IP address in batches
$batchSize = 1000; // Adjust batch size as needed
$start = 0;
$duplicateIds = [];

do {
  // Fetch a batch of duplicate proxies
  $stmt = $pdo->prepare("SELECT SUBSTR(proxy, 0, INSTR(proxy, ':')) AS ip, COUNT(*) AS count_duplicates
  FROM proxies
  GROUP BY ip
  HAVING COUNT(*) > 1
  ORDER BY RANDOM()
  LIMIT :start, :batchSize");
  $stmt->bindParam(':start', $start, PDO::PARAM_INT);
  $stmt->bindParam(':batchSize', $batchSize, PDO::PARAM_INT);
  $stmt->execute();
  $duplicateIpCounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
  $startTime = microtime(true);

  // Display the results
  foreach ($duplicateIpCounts as $ipInfo) {
    $ip = $ipInfo['ip'];
    // Skip invalid IP
    if (!isValidIp($ip)) {
      continue;
    }
    // Check if execution time has exceeded the maximum allowed time
    $elapsedTime = microtime(true) - $startTime;
    if ($elapsedTime > $maxExecutionTime) {
      // Execution time exceeded
      echo "Execution time exceeded maximum allowed time of {$maxExecutionTime} seconds." . PHP_EOL;
      exit(0);
    }

    // Re-count the same IP
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM proxies WHERE SUBSTR(proxy, 0, INSTR(proxy, ':')) = :ip");
    $stmt->bindParam(':ip', $ip, PDO::PARAM_STR);
    $stmt->execute();
    $count = $stmt->fetchColumn();

    if (intval($count) <= 1) {
      continue;
    }

    echo "Count of proxies with IP $ip: $count\n";
    // Fetch all rows matching the IP address (including port)
    $stmt = $pdo->prepare("SELECT \"_rowid_\",* FROM \"main\".\"proxies\" WHERE SUBSTR(proxy, 0, INSTR(proxy, ':')) = :ip ORDER BY RANDOM() LIMIT 0, 49999;");
    $stmt->bindParam(':ip', $ip, PDO::PARAM_STR);
    $stmt->execute();
    $ipRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($ipRows)) {
      $keepRow = null;
      foreach ($ipRows as $row) {
        $proxy = $row['proxy'];
        if ($row['status'] == 'active') {
          continue;
        }
        if (!isValidProxy(trim($proxy))) {
          // Proxy is invalid, delete the row
          $deleteStmt = $pdo->prepare("DELETE FROM proxies WHERE \"_rowid_\" = :id AND \"proxy\" = :proxy");
          $deleteStmt->bindParam(':id', $row['id'], PDO::PARAM_INT);
          $deleteStmt->bindParam(':proxy', $proxy, PDO::PARAM_STR);
          $deleteStmt->execute();

          echo "Deleted invalid proxy: $proxy\n";
        } else {
          if (!$keepRow) {
            // initialize keep row
            $keepRow = $row;
          }
          if (isPortOpen($proxy)) {
            echo "$proxy port open\n";
            // keep open port
            $keepRow = $row;
          } elseif ($keepRow['proxy'] !== $proxy) {
            // Delete closed port
            $deleteStmt = $pdo->prepare("DELETE FROM proxies WHERE \"_rowid_\" = :id AND \"proxy\" = :proxy");
            $deleteStmt->bindParam(':id', $row['id'], PDO::PARAM_INT);
            $deleteStmt->bindParam(':proxy', $proxy, PDO::PARAM_STR);
            $deleteStmt->execute();

            echo "Deleted closed port: $proxy\n";
          }
        }
      }
    }
  }

  // Increment start for the next batch
  $start += $batchSize;
} while (!empty($duplicateIpCounts));

// Close connection
$pdo = null;
