<?php

// remove duplicate IP from database

require_once __DIR__ . '/../php_backend/shared.php';

global $isCli, $proxy_db;

use PhpProxyHunter\Scheduler;

if (!$isCli) {
  exit('only CLI allowed');
}

$max_checks       = 500; // max proxies to be checked
$maxExecutionTime = 120; // max 120s execution time
$endless          = false;
$perform_delete   = false;

if ($isCli) {
  $short_opts = 'p:m::';
  $long_opts  = [
    'proxy:',
    'max::',
    'userId::',
    'lockFile::',
    'runner::',
    'admin::',
    'endless::',
    'delete::',
  ];
  $options        = getopt($short_opts, $long_opts);
  $perform_delete = !empty($options['delete']); // --delete=true
  if (!empty($options['max'])) { // --max=100
    $max = intval($options['max']);
    if ($max > 0) {
      $max_checks = $max;
    }
  }
  if (!empty($options['admin']) && $options['admin'] !== 'false') { // --admin=true
    $isAdmin = true;
  }
  if (!empty($options['endless'])) { // --endless=true
    $endless = true;
  }
}

if ($isAdmin || $endless) {
  // set time limit 10 minutes for admin and 600 minutes for endless mode
  $maxExecutionTime = $endless ? 600 * 60 : 10 * 60;
  // disable execution limit
  set_time_limit(0);
}

$lockFilePath = tmp() . '/runners/' . basename(__FILE__, '.php') . '.lock';
if ($endless) {
  $lockFilePath = tmp() . '/runners/' . basename(__FILE__, '.php') . '-endless.lock';
}

$statusFile = dirname(__DIR__) . '/status.txt';

// Ensure the directory exists for the lock file
$lockDir = dirname($lockFilePath);
if (!is_dir($lockDir)) {
  mkdir($lockDir, 0755, true);
}

// Check if the lock file exists
if (file_exists($lockFilePath)) {
  if ($endless || !$isAdmin) {
    // Exit with a message if another process is running and the conditions are met
    exit(date(DATE_RFC3339) . ' another process still running ' . basename(__FILE__, '.php') . PHP_EOL);
  }
} else {
  // Create the lock file and write the current date
  write_file($lockFilePath, date(DATE_RFC3339));
  // Create or update the status file with the message 'filter-ports'
  write_file($statusFile, 'filter-ports');
}

Scheduler::register(function () use ($lockFilePath, $statusFile) {
  if (file_exists($lockFilePath) && is_file($lockFilePath)) {
    unlink($lockFilePath);
  }
  file_put_contents($statusFile, 'idle');
}, 'z_Exit_' . md5(__FILE__));

$db = $proxy_db;
/**
 * @var PDO
 */
$pdo = $db->db->pdo;

// Determine database type using PDO getAttribute method
$driver  = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
$isMySQL = $driver === 'mysql';

// Step 1: Identify and process duplicates based on IP address in batches
$batchSize    = 1000; // Adjust batch size as needed
$start        = 0;
$duplicateIds = [];

do {
  // Set database-specific SQL syntax
  if ($isMySQL) {
    $substrFunction = "SUBSTRING_INDEX(proxy, ':', 1)";
    $randomFunction = 'RAND()';
  } else {
    $substrFunction = "SUBSTR(proxy, 1, INSTR(proxy, ':') - 1)";
    $randomFunction = 'RANDOM()';
  }

  // Fetch a batch of duplicate proxies
  $stmt = $pdo->prepare("SELECT ip, COUNT(*) AS count_duplicates
  FROM (
    SELECT $substrFunction AS ip
    FROM proxies
    WHERE status != 'active'
    AND status != 'untested'
    AND last_check < ?
  ) AS filtered_proxies
  GROUP BY ip
  HAVING COUNT(*) > 1
  ORDER BY $randomFunction
  LIMIT ? OFFSET ?");

  $sevenDaysAgo = date('Y-m-d H:i:s', strtotime('-7 days'));
  $stmt->bindParam(1, $sevenDaysAgo, PDO::PARAM_STR);
  $stmt->bindParam(2, $batchSize, PDO::PARAM_INT);
  $stmt->bindParam(3, $start, PDO::PARAM_INT);
  $stmt->execute();
  $duplicateIpCounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
  $startTime         = microtime(true);

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
    $stmt = $pdo->prepare("SELECT COUNT(*) as count
                       FROM proxies
                       WHERE $substrFunction = :ip
                       AND status != 'active'
                       AND status != 'untested'
                       AND last_check < :date");
    $stmt->bindParam(':ip', $ip, PDO::PARAM_STR);
    $stmt->bindParam(':date', $sevenDaysAgo, PDO::PARAM_STR);
    $stmt->execute();
    $count = $stmt->fetchColumn();

    if (intval($count) <= 1) {
      continue;
    }

    echo "Count of proxies with IP $ip: $count\n";
    // Fetch all rows matching the IP address (including port)
    // Exclude active proxies
    if ($isMySQL) {
      $selectClause = 'SELECT id, proxy, status, last_check, type, region, city, country, private, username, password';
      $fromClause   = 'FROM proxies';
    } else {
      $selectClause = 'SELECT "_rowid_", *';
      $fromClause   = 'FROM "main"."proxies"';
    }

    $stmt = $pdo->prepare("$selectClause $fromClause
                       WHERE $substrFunction = :ip
                       AND status != 'active'
                       AND status != 'untested'
                       AND last_check < :date
                       ORDER BY $randomFunction LIMIT 49999");
    $stmt->bindParam(':ip', $ip, PDO::PARAM_STR);
    $stmt->bindParam(':date', $sevenDaysAgo, PDO::PARAM_STR);
    $stmt->execute();
    $ipRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($ipRows)) {
      $keepRow = null;
      // shuffle ips
      shuffle($ipRows);
      foreach ($ipRows as $row) {
        $log           = '';
        $proxy         = $row['proxy'];
        $isAlreadyDead = false;
        if (!empty($row['last_check'])) {
          $deadStatus = $row['status'] == 'port-closed' || $row['status'] == 'dead';
          // treat already dead proxy more than 1 month
          $isAlreadyDead = $deadStatus && isDateRFC3339OlderThanHours($row['last_check'], 73000);
        }
        if ($row['status'] == 'active') {
          continue;
        }
        if (!isValidProxy(trim($proxy))) {
          // Proxy is invalid, delete the row
          if ($isMySQL) {
            $deleteStmt = $pdo->prepare('DELETE FROM proxies WHERE id = :id AND proxy = :proxy');
            $deleteStmt->bindParam(':id', $row['id'], PDO::PARAM_INT);
          } else {
            $deleteStmt = $pdo->prepare('DELETE FROM proxies WHERE "_rowid_" = :id AND "proxy" = :proxy');
            $deleteStmt->bindParam(':id', $row['_rowid_'], PDO::PARAM_INT);
          }
          $deleteStmt->bindParam(':proxy', $proxy, PDO::PARAM_STR);
          $deleteStmt->execute();

          $log = "[FILTER-PORT] $proxy invalid proxy [DELETED]\n";
        } else {
          if (!$keepRow) {
            // initialize keep row
            $keepRow = $row;
          }
          // Check if the proxy was already checked this month
          // always check when execute with --delete=true
          if (!wasCheckedThisMonth($pdo, $proxy) || $perform_delete) {
            if (isPortOpen($proxy)) {
              echo "$proxy port open\n";
              $db->updateData($proxy, ['status' => 'untested'], false);
              // Update the last_check timestamp
              $lastCheck  = date(DATE_RFC3339); // Assign date to a variable
              $updateStmt = $pdo->prepare('UPDATE proxies SET last_check = :last_check WHERE proxy = :proxy');
              $updateStmt->bindParam(':last_check', $lastCheck, PDO::PARAM_STR); // Use variable here
              $updateStmt->bindParam(':proxy', $proxy, PDO::PARAM_STR);
              $updateStmt->execute();
              // keep open port
              $keepRow = $row;
            } elseif ($keepRow['proxy'] !== $proxy) {
              if ($perform_delete || $isAlreadyDead) {
                // Delete closed port
                $deleteStmt = $pdo->prepare('DELETE FROM proxies WHERE id = :id AND proxy = :proxy');
                $deleteStmt->bindParam(':id', $row['id'], PDO::PARAM_INT);
                $deleteStmt->bindParam(':proxy', $proxy, PDO::PARAM_STR);
                $deleteStmt->execute();
                $log = "[FILTER-PORT] $proxy port closed [DELETED]" . PHP_EOL;
              } else {
                $db->updateData($proxy, ['status' => 'port-closed'], false);
                $log = "[FILTER-PORT] $proxy port closed" . PHP_EOL;
              }
            }
          } else {
            $log = "[FILTER-PORT] $proxy [SKIPPED]" . PHP_EOL;
          }
        }
        if (!empty($log)) {
          echo $log;
        }
      }
    }
  }

  // write working proxies
  write_working();

  // Increment start for the next batch
  $start += $batchSize;
} while (!empty($duplicateIpCounts));

// Close connection
$pdo = null;

/**
 * Check whether a proxy was checked in the current month.
 *
 * This inspects the `proxies` table for a row matching the provided proxy
 * where the `last_check` timestamp falls within the current year-month.
 * For MySQL the query uses DATE_FORMAT(last_check, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m').
 * For other drivers (e.g. SQLite) it uses a prefix match on the ISO timestamp (YYYY-MM%).
 *
 * @param \PDO  $pdo   PDO connection used to query the proxies table.
 * @param string $proxy Proxy string (example: "1.2.3.4:8080").
 * @return bool True if the proxy has at least one `last_check` in the current month, false otherwise.
 * @throws \PDOException If the query execution fails.
 */
function wasCheckedThisMonth(\PDO $pdo, string $proxy) {
  // Determine driver and build a compatible SQL condition for "same month"
  $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
  if ($driver === 'mysql') {
    // MySQL: use DATE_FORMAT
    $sql = "SELECT COUNT(*) FROM proxies WHERE proxy = :proxy AND DATE_FORMAT(last_check, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')";
  } else {
    // Fallback: compare YYYY-MM prefix (works for SQLite and other engines storing ISO timestamps)
    $monthPrefix = date('Y-m');
    $sql         = "SELECT COUNT(*) FROM proxies WHERE proxy = :proxy AND last_check LIKE :monthPrefix || '%'";
  }

  $stmt = $pdo->prepare($sql);
  $stmt->bindParam(':proxy', $proxy, PDO::PARAM_STR);
  if ($driver === 'mysql') {
    $stmt->execute();
  } else {
    // For engines using || as concatenation (SQLite) the placeholder needs the prefix value
    // Use bindValue to ensure correct value type
    $stmt->bindValue(':monthPrefix', $monthPrefix, PDO::PARAM_STR);
    $stmt->execute();
  }

  return $stmt->fetchColumn() > 0;
}

// Function to check if the proxy was checked this week
function wasCheckedThisWeek($pdo, $proxy) {
  $startOfWeek = date('Y-m-d', strtotime('last sunday'));
  $stmt        = $pdo->prepare('SELECT COUNT(*) FROM proxies WHERE proxy = :proxy AND last_check >= :start_of_week');
  $stmt->bindParam(':proxy', $proxy, PDO::PARAM_STR);
  $stmt->bindParam(':start_of_week', $startOfWeek, PDO::PARAM_STR);
  $stmt->execute();
  return $stmt->fetchColumn() > 0;
}

function write_working() {
  global $db;
  echo '[FILTER-PORT] writing working proxies' . PHP_EOL;
  return writing_working_proxies_file($db);
}
