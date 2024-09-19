<?php

require __DIR__ . '/proxyCheckerParallel-func.php';

global $isCli, $isAdmin, $isCli;

use PhpProxyHunter\Proxy;
use PhpProxyHunter\ProxyDB;
use PhpProxyHunter\Scheduler;
use PhpProxyHunter\Server;

$db = new ProxyDB();
$str = '';
// default limit proxy to check
$max = 100 + $db->countWorkingProxies();
// default lock file
$lockFile = __DIR__ . '/proxyChecker.lock';

if (!$isCli) {
  // set output buffering to zero
  ini_set('output_buffering', 0);
  if (ob_get_level() == 0) {
    ob_start();
  }
  header("Access-Control-Allow-Origin: *");
  header("Access-Control-Allow-Headers: *");
  header("Access-Control-Allow-Methods: *");
  header('Content-Type: text/plain; charset=UTF-8');

  // web server admin
  $isAdmin = !empty($_SESSION['admin']) && $_SESSION['admin'] === true;

  // setup lock file
  $id = Server::getRequestIP();
  if (empty($id)) {
    $id .= Server::useragent();
  }
  $user_id = getUserId();
  $webLockFile = tmp() . "/runners/$user_id-parallel-web-" . sanitizeFilename($id) . '.lock';
  if (file_exists($webLockFile) && !$isAdmin) {
    exit(date(DATE_RFC3339) . ' another process still running (web lock file is locked) ' . basename(__FILE__, '.php') . PHP_EOL);
  } else {
    write_file($webLockFile, date(DATE_RFC3339));
  }

  // delete web lock file after webserver closed
  Scheduler::register(function () use ($webLockFile) {
    delete_path($webLockFile);
  }, 'webserver-close-' . md5(__FILE__));
  // parse post data
  if (isset($_REQUEST['proxy'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      // post data with body key/name proxy
      $parse = parsePostData();
      if ($parse) {
        if (isset($parse['proxy'])) {
          $str = rawurldecode($parse['proxy']);
        } else {
          $str = rawurldecode(json_encode($parse));
        }
      }
    } else {
      // proxyCheckerParallel.php?proxy=ANY_STRING_CONTAINS_PROXY
      $str = rawurldecode($_REQUEST['proxy']);
    }
  }

  // check base64 encoded post data
  if (isBase64Encoded($str)) {
    $str = base64_decode(trim($str));
  }

  // web server run parallel in background
  // avoid bad response or hangs whole web server
  $file = __FILE__;
  $runner_output_file = __DIR__ . '/proxyChecker.txt';
  $cmd = "php " . escapeshellarg($file);

  $user_id = getUserId();
  $runner = tmp() . "/runners/parallel-cli-$user_id-$id" . ($isWin ? '.bat' : ".sh");
  $cliLockFile = tmp() . "/runners/parallel-cli-$user_id-$id.lock";
  $uid = getUserId();
  $cmd .= " --userId=" . escapeshellarg($uid);
  $cmd .= " --lockFile=" . escapeshellarg(unixPath($cliLockFile));
  $cmd .= " --runner=" . escapeshellarg(unixPath($runner));
  // re-encode base64
  $cmd .= " --proxy=" . escapeshellarg(base64_encode($str));
  $cmd .= " --max=" . escapeshellarg("30");
  $cmd .= " --admin=" . escapeshellarg($isAdmin ? 'true' : 'false');

  // Generate the command to run in the background
  $cmd = sprintf("%s > %s 2>&1 & echo $! >> %s", $cmd, escapeshellarg($runner_output_file), escapeshellarg($webLockFile));

  // Write the command to the runner script
  if (write_file($runner, $cmd)) {
    echo $cmd . "\n\n";
    // Execute the runner script in the background
    runBashOrBatch($runner);
  } else {
    echo "[CHECKER-PARALLEL] failed writing $runner\n\n";
  }
}

// process only for CLI

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
  $isAdmin = !empty($options['admin']) && $options['admin'] !== 'false';
  if (!$isAdmin) {
    // only apply lock file for non-admin command
    if (!empty($options['lockFile'])) {
      $lockFile = $options['lockFile'];
      if (file_exists($options['lockFile'])) {
        exit(date(DATE_RFC3339) . ' another process still running (' . $options['lockFile'] . ' is locked) ' . PHP_EOL);
      }
      write_file($options['lockFile'], '');
    }
  }
  // always schedule to remove lock file
  if (!empty($options['lockFile'])) {
    Scheduler::register(function () use ($options) {
      delete_path($options['lockFile']);
    }, 'release-cli-lock');
  }

  if (!empty($options['runner'])) {
    // remove web server runner after finish
    Scheduler::register(function () use ($options) {
      delete_path($options['runner']);
    }, 'release-runner-script');
  }

  if (!empty($options['max'])) {
    $max_test = intval($options['max']);
    if ($max_test > 0) {
      $max = $max_test;
    }
  }

  if (!empty($options['proxy'])) {
    if (isBase64Encoded($options['proxy'])) {
      $str = base64_decode($options['proxy']);
    } else {
      $str = $options['proxy'];
    }
  }

  $proxies = extractProxies($str, $db);
  echo "[CHECKER-PARALLEL] checking " . count($proxies) . " proxies\n";
  $str_to_remove = [];

  if (empty($proxies)) {
    $db_data = $db->getUntestedProxies(100);
    if (count($db_data) < 100) {
      // get dead proxies last checked more than 24 hours ago
      $dead_data = array_filter($db->getDeadProxies(100), function ($item) {
        if (empty($item['last_check'])) {
          return true;
        }
        return isDateRFC3339OlderThanHours($item['last_check'], 24);
      });
      $db_data = array_merge($db_data, $dead_data);
    }
    $working_data = array_filter($db->getWorkingProxies(100), function ($item) {
      if (empty($item['last_check'])) {
        return true;
      }
      return isDateRFC3339OlderThanHours($item['last_check'], 24);
    });
    $db_data = array_merge($db_data, $working_data);
    $db_data_map = array_map(function ($item) {
      // transform array into Proxy instance same as extractProxies result
      $wrap = new Proxy($item['proxy']);
      foreach ($item as $key => $value) {
        if (property_exists($wrap, $key)) {
          $wrap->$key = $value;
        }
      }
      if (!empty($item['username']) && !empty($item['password'])) {
        $wrap->username = $item['username'];
        $wrap->password = $item['password'];
      }
      return $wrap;
    }, $db_data);
    $proxies = array_filter($db_data_map, function (Proxy $item) use ($db) {
      if (!isValidProxy($item->proxy)) {
        if (!empty($item->proxy)) {
          $db->remove($item->proxy);
        }
        return false;
      }
      // skip already checked proxy today
      // if ($item->last_check && !empty($item->status) && $item->status != 'untested') {
      //   if (isDateRFC3339OlderThanHours($item->last_check, 24)) {
      //     return true;
      //   }
      // }
      return true;
    });
  }

  // perform checks
  if (!empty($proxies)) {
    set_time_limit(0);
    checkProxyInParallel($proxies);
  }
}
