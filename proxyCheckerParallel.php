<?php

require __DIR__ . '/func-proxy.php';

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

/**
 * @param Proxy[] $proxies
 * @param string|null $custom_endpoint
 * @return void
 */
function checkProxyInParallel(array $proxies, ?string $custom_endpoint = null, ?bool $print_headers = true)
{
  global $isCli, $max, $str_to_remove, $lockFile;
  $user_id = getUserId();
  $config = getConfig($user_id);
  $endpoint = 'https://www.example.com';
  $headers = [
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:127.0) Gecko/20100101 Firefox/127.0'
  ];
  if (strtolower($user_id) != 'cli') {
    // get endpoint from user data
    $endpoint = trim($config['endpoint']);
    $headers = array_filter($config['headers']);
  }
  // prioritize custom endpoint
  if (!empty($custom_endpoint)) {
    $endpoint = $custom_endpoint;
  }
  if ($print_headers) {
    echo "[CHECKER-PARALLEL] CONFIG" . PHP_EOL;
    echo "User $user_id " . date(DATE_RFC3339) . PHP_EOL;
    echo "GET $endpoint" . PHP_EOL;
    echo implode(PHP_EOL, $headers) . PHP_EOL . PHP_EOL;
  }
  $db = new ProxyDB();
  $statusFile = __DIR__ . "/status.txt";
  Scheduler::register(function () use ($lockFile, $statusFile) {
    // release main lock files
    delete_path($lockFile);
    write_file($statusFile, 'idle');
  }, 'release-main-lock');
  for ($i = 0; $i < rand(1, 4); $i++) {
    shuffle($proxies);
  }
  // limit proxies by $max
  $proxies = array_slice($proxies, 0, $max);
  $iterator = new ArrayIterator($proxies);
  $combinedIterable = new MultipleIterator(MultipleIterator::MIT_NEED_ALL);
  $combinedIterable->attachIterator($iterator);
  $counter = 0;
  $startTime = microtime(true);
  foreach ($combinedIterable as $index => $item) {
    if (empty($item[0]->proxy)) {
      continue;
    }
    if (!$isCli) {
      // Check if execution time has exceeded the maximum allowed time
      $elapsedTime = microtime(true) - $startTime;
      if ($elapsedTime > 60) {
        // Execution time exceeded
        echo "Execution time exceeded maximum allowed time of 60 seconds." . PHP_EOL;
        break;
      }
    }
    $run_file = tmp() . '/runners/' . basename(__FILE__, '.php') . '-' . sanitizeFilename($item[0]->proxy) . '.txt';
    // schedule release current proxy thread lock
    Scheduler::register(function () use ($run_file) {
      delete_path($run_file);
    }, md5($run_file));
    if (file_exists($run_file)) {
      continue;
    }
    // write lock
    write_file($run_file, '');
    write_file($statusFile, 'running in parallel');
    write_file($lockFile, 'running in parallel');
    $counter++;
    if (!isPortOpen($item[0]->proxy)) {
      $db->updateStatus($item[0]->proxy, 'port-closed');
      echo "[CHECKER-PARALLEL] $counter. {$item[0]->proxy} port closed" . PHP_EOL;
    } else {
      $ch = [
        buildCurl($item[0]->proxy, 'http', $endpoint, $headers, $item[0]->username, $item[0]->password),
        buildCurl($item[0]->proxy, 'socks4', $endpoint, $headers, $item[0]->username, $item[0]->password),
        buildCurl($item[0]->proxy, 'socks5', $endpoint, $headers, $item[0]->username, $item[0]->password)
      ];

      $protocols = [];
      $mh = curl_multi_init();
      foreach ($ch as $handle_index => $handle) {
        $protocol = $handle_index === 0 ? 'http' : ($handle_index === 1 ? 'socks4' : ($handle_index === 2 ? 'socks5' : ''));
        $protocols[$handle_index] = $protocol;
        curl_multi_add_handle($mh, $handle);
      }
      // Record the start time
      $startTime = microtime(true);
      $running = null;
      do {
        curl_multi_exec($mh, $running);
        // Wait a short time before continuing to avoid consuming too much CPU
        curl_multi_select($mh);
      } while ($running > 0);
      // Record the end time
      $endTime = microtime(true);

      // Calculate the total latency
      $latency = round(($endTime - $startTime) * 1000);
      $isPrivate = false;
      $isWorking = false;
      foreach ($ch as $handle_index => $handle) {
        $http_status = curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $http_status_valid = $http_status == 200 || $http_status == 201 || $http_status == 202 || $http_status == 204 ||
          $http_status == 301 || $http_status == 302 || $http_status == 304;
        $protocol = $protocols[$handle_index];
        if ($http_status_valid) {
          $info = curl_getinfo($handle);
          $response = curl_multi_getcontent($handle);
          // check if not azenv proxy
          if (is_string($response) && !checkRawHeadersKeywords($response)) {
            $header_size = curl_getinfo($handle, CURLINFO_HEADER_SIZE);
            $response_header = substr($response, 0, $header_size);
            $match_private = [];

            // is private proxy?
            $isPrivate = stripos($response_header, 'Proxy-Authorization:') !== false;

            if (curl_errno($handle) || $response === false) {
              $error_msg = curl_error($handle);
              if (preg_match('/no authentication method was acceptable/mi', $error_msg)) {
                $isPrivate = true;
              }
            } else {
              // check proxy private by redirected to gateway url
              if (!$isPrivate) {
                $finalUrl = $info['url'];
                $pattern = '/^https?:\/\/(?:www\.gstatic\.com|gateway\.(zs\w+)\.[a-zA-Z]{2,})(?::\d+)?\/.*(?:origurl)=/i';
                $mP = preg_match($pattern, $finalUrl, $match_private);
                $isPrivate = $mP > 0;
              }
            }
            $priv_msg = $isPrivate ? "true " . implode("|", $match_private) : "false";
            $log_msg =  "[CHECKER-PARALLEL] $counter. $protocol://{$item[0]->proxy} is working (private $priv_msg)\n";
            echo $log_msg;
            $isWorking = !$isPrivate;
          }
        }
      }

      // close
      foreach ($ch as $handle) {
        curl_multi_remove_handle($mh, $handle);
        curl_close($handle);
      }
      curl_multi_close($mh);

      if ($isWorking) {
        $data = [
          'type' => implode('-', $protocols),
          'status' => 'active',
          'private' => $isPrivate ? 'true' : 'false',
          'latency' => $latency,
          'https' => str_starts_with($endpoint, "https://") ? 'true' : 'false'
        ];
        $db->updateData($item[0]->proxy, $data);
        foreach (['http', 'socks5', 'socks4'] as $proxy_type) {
          $anonymity = get_anonymity($item[0]->proxy, $proxy_type, $item[0]->username, $item[0]->password);
          if (!empty($anonymity)) {
            $db->updateData($item[0]->proxy, ['anonymity' => strtolower($anonymity)]);
            break;
          }
        }
        if (empty($item[0]->timezone) || empty($item[0]->country) || empty($item[0]->lang)) {
          foreach ($protocols as $protocol) {
            get_geo_ip($item[0]->proxy, $protocol, $db);
          }
        }
        if (empty($item[0]->useragent)) {
          $item[0]->useragent = randomWindowsUa();
          $db->updateData($item[0]->proxy, ['useragent' => $item[0]->useragent]);
        }
        if (empty($item[0]->webgl_renderer) || empty($item[0]->browser_vendor) || empty($item[0]->webgl_vendor)) {
          $webgl = random_webgl_data();
          $db->updateData($item[0]->proxy, [
            'webgl_renderer' => $webgl->webgl_renderer,
            'webgl_vendor' => $webgl->webgl_vendor,
            'browser_vendor' => $webgl->browser_vendor
          ]);
        }
        // write working proxies
        write_working();
      } else {
        $db->updateStatus($item[0]->proxy, 'dead');
        echo "[CHECKER-PARALLEL] $counter. {$item[0]->proxy} dead" . PHP_EOL;
        // re-check with non-https endpoint
        checkProxyInParallel([$item[0]->proxy], 'http://httpforever.com/', false);
      }

      // push proxy to be removed
      $str_to_remove[] = $item[0]->proxy;
      schedule_remover();
    }

    // flush for live echo
    if (ob_get_level() > 0) {
      // Flush the buffer to the client
      ob_flush();
      // Optionally, you can also flush the PHP internal buffer
      flush();
    }
  }

  // write working proxies
  write_working();

  // End buffering and send the buffer
  if (ob_get_level() > 0) {
    ob_end_flush();
  }
}

function write_working()
{
  global $db;
  echo "[CHECKER-PARALLEL] writing working proxies" . PHP_EOL;
  $data = parse_working_proxies($db);
  file_put_contents(__DIR__ . '/working.txt', $data['txt']);
  file_put_contents(__DIR__ . '/working.json', json_encode($data['array']));
  file_put_contents(__DIR__ . '/status.json', json_encode($data['counter']));
  return $data;
}

function cleanUp()
{
  $directory = tmp() . '/runners/';

  // Get the current time
  $current_time = time();

  // Define the time threshold (10 minutes = 600 seconds)
  $time_threshold = 600;

  // Check if the directory exists
  if (is_dir($directory)) {
    // Open the directory
    if ($handle = opendir($directory)) {
      // Loop through the directory contents
      while (false !== ($entry = readdir($handle))) {
        // Skip the current (.) and parent (..) directories
        if ($entry != '.' && $entry != '..') {
          $full_path = $directory . $entry;

          // Check the file/folder creation time
          $creation_time = filectime($full_path);

          // Calculate the age of the file/folder
          $age = $current_time - $creation_time;

          // Delete if older than the threshold
          if ($age > $time_threshold) {
            delete_path($full_path);
          }
        }
      }
      // Close the directory handle
      closedir($handle);
    }
  }
}

function schedule_remover()
{
  global $str_to_remove;
  if (!empty($str_to_remove)) {
    // remove already indexed proxies
    Scheduler::register(function () use ($str_to_remove) {
      $files = [__DIR__ . '/dead.txt', __DIR__ . '/proxies.txt', __DIR__ . '/proxies-all.txt'];
      $assets = array_filter(getFilesByExtension(__DIR__ . '/assets/proxies'), function ($fn) {
        return strpos($fn, 'added-') !== false;
      });
      $files = array_merge($files, $assets);
      $files = array_filter($files, 'file_exists');
      $files = array_map('realpath', $files);
      foreach ($files as $file) {
        $remove = removeStringFromFile($file, $str_to_remove);
        if ($remove == 'success') {
          echo "[CHECKER-PARALLEL] removed indexed proxies from " . basename($file) . PHP_EOL;
          sleep(1);
          removeEmptyLinesFromFile($file);
        }
        sleep(1);
        if (filterIpPortLines($file) == 'success') {
          echo "[CHECKER-PARALLEL] non IP:PORT lines removed from " . basename($file) . PHP_EOL;
        }
        sleep(1);
      }
      cleanUp();
    }, "remove indexed proxies");
  }
}
