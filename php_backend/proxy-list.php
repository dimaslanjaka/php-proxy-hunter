<?php

// Server-side proxy list for DataTables
// Uses existing $proxy_db directly — does NOT instantiate or validate it.

require_once __DIR__ . '/shared.php';

use PhpProxyHunter\Server;

// Allow CORS
Server::allowCors(true);

$isCli = is_cli();

// Only allow if captcha passed
if (empty($_SESSION['captcha']) && !$isCli) {
  http_response_code(403);
  echo json_encode(['error' => true, 'message' => 'Captcha not verified']);
  exit;
}

$refresh = refreshDbConnections(true);
/** @var \PhpProxyHunter\CoreDB $core_db */
$core_db = $refresh['core_db'];
/** @var \PhpProxyHunter\ProxyDB $proxy_db */
$proxy_db = $refresh['proxy_db'];

// helper to parse incoming payload if needed
$request = parseQueryOrPostBody();

if (!$isCli) {
  header('Content-Type: application/json; charset=utf-8');
}

try {
  // DataTables draw param
  $draw = isset($request['draw']) ? (int)$request['draw'] : 0;

  // Paging: DataTables uses `start` and `length`
  $start  = isset($request['start']) ? max(0, (int)$request['start']) : 0;
  $length = isset($request['length']) ? (int)$request['length'] : 10;
  // Disallow "show all" (-1) to avoid excessive memory/CPU usage on small VPSes.
  // Instead, cap to a safe maximum number of rows per page.
  $MAX_PER_PAGE = 1000;
  if ($length === -1) {
    // Treat -1 as request for many rows; cap to a safe maximum instead of returning everything.
    $length = $MAX_PER_PAGE;
  }
  // Clamp length to a sane range to avoid abuse and accidental overloads.
  $length  = max(1, min($length, $MAX_PER_PAGE));
  $page    = ($length && $length > 0) ? (int)floor($start / $length) + 1 : 1;
  $perPage = $length;

  // Search value (DataTables nested or simple q/proxy)
  $search = '';
  if (isset($request['search']) && is_array($request['search']) && isset($request['search']['value'])) {
    $search = trim((string)$request['search']['value']);
  } elseif (isset($request['q'])) {
    $search = trim((string)$request['q']);
  } elseif (isset($request['proxy'])) {
    $search = trim((string)$request['proxy']);
  }

  // Optional status filter
  $statusFilter = '';
  if (isset($request['status'])) {
    $statusFilter = trim((string)$request['status']);
  }
  // Optional type filter (http, https, socks4, socks5, ssl, tun2socks)
  $typeFilter = '';
  if (isset($request['type'])) {
    $typeFilter = trim((string)$request['type']);
  }
  // Optional country filter
  $countryFilter = '';
  if (isset($request['country'])) {
    $countryFilter = trim((string)$request['country']);
  }
  // Optional city filter
  $cityFilter = '';
  if (isset($request['city'])) {
    $cityFilter = trim((string)$request['city']);
  }

  // Special action: return distinct statuses when requested
  if (isset($request['get_statuses']) && $request['get_statuses']) {
    $stmt     = $proxy_db->db->pdo->query('SELECT DISTINCT status FROM proxies ORDER BY status');
    $statuses = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode(['statuses' => $statuses], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }

  // Ordering: DataTables sends `order[0][column]` and `order[0][dir]` and `columns[i][data]`
  $orderBy = null;
  if (isset($request['order']) && is_array($request['order']) && isset($request['order'][0]['column'])) {
    $colIndex = (int)$request['order'][0]['column'];
    $dir      = isset($request['order'][0]['dir']) && strtolower($request['order'][0]['dir']) === 'desc' ? 'DESC' : 'ASC';
    // Determine column name from columns array if present
    if (isset($request['columns']) && isset($request['columns'][$colIndex]) && !empty($request['columns'][$colIndex]['data'])) {
      $col = $request['columns'][$colIndex]['data'];
      // sanitize column name (allow basic alphanum and underscore)
      $col = preg_replace('/[^a-zA-Z0-9_]/', '', $col);
      if (!empty($col)) {
        $orderBy = $col . ' ' . $dir;
      }
    }
  }

  // Prefer using existing PDO instance from $proxy_db to reuse connections and helpers
  $pdo = $proxy_db->db->pdo;
  // Get driver name "mysql", "sqlite".
  $driver = $core_db->driver;

  // total records
  $stmtTotal    = $pdo->query('SELECT COUNT(*) as cnt FROM proxies');
  $recordsTotal = (int)$stmtTotal->fetch(PDO::FETCH_ASSOC)['cnt'];

  // recordsFiltered: count matching rows according to active filters
  $recordsFiltered = $recordsTotal;
  $whereParts      = [];
  $countParams     = [];
  if (!empty($search)) {
    $whereParts[] = 'proxy LIKE :search';
    // Escape SQL LIKE wildcard characters to perform literal substring search
    $esc                    = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
    $countParams[':search'] = '%' . $esc . '%';
  }
  if (!empty($statusFilter)) {
    $whereParts[]           = 'status = :status';
    $countParams[':status'] = $statusFilter;
  }
  if (!empty($typeFilter)) {
    // If filtering for tun2socks, match proxies with tun2socks numeric value > 0.
    if (strtolower($typeFilter) === 'tun2socks') {
      $whereParts[] = '(tun2socks + 0) > 0';
    } elseif (strtolower($typeFilter) === 'ssl') {
      // If filtering for SSL, match either the type column or the https flag.
      $whereParts[]               = '(type LIKE :type OR https = :https_true OR https = :https_one OR https = :https_int)';
      $countParams[':type']       = '%' . $typeFilter . '%';
      $countParams[':https_true'] = 'true';
      $countParams[':https_one']  = '1';
      $countParams[':https_int']  = 1;
    } else {
      $whereParts[]         = 'type LIKE :type';
      $countParams[':type'] = '%' . $typeFilter . '%';
    }
  }
  if (!empty($countryFilter)) {
    $whereParts[]            = 'country LIKE :country';
    $escCountry              = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $countryFilter);
    $countParams[':country'] = '%' . $escCountry . '%';
  }
  if (!empty($cityFilter)) {
    $whereParts[]         = 'city LIKE :city';
    $escCity              = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $cityFilter);
    $countParams[':city'] = '%' . $escCity . '%';
  }
  if (!empty($whereParts)) {
    $countSql = 'SELECT COUNT(*) as cnt FROM proxies WHERE ' . implode(' AND ', $whereParts);
    $stmt     = $pdo->prepare($countSql);
    $stmt->execute($countParams);
    $recordsFiltered = (int)$stmt->fetch(PDO::FETCH_ASSOC)['cnt'];
  }

  // Fetch rows with pagination and ordering
  // Build SQL query
  $whereParts = [];
  $where      = '';
  $params     = [];
  if (!empty($search)) {
    $whereParts[] = 'proxy LIKE :search';
    // Escape SQL LIKE wildcard characters to perform literal substring search
    $esc               = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
    $params[':search'] = '%' . $esc . '%';
  }
  if (!empty($statusFilter)) {
    $whereParts[]      = 'status = :status';
    $params[':status'] = $statusFilter;
  }
  if (!empty($typeFilter)) {
    if (strtolower($typeFilter) === 'tun2socks') {
      $whereParts[] = '(tun2socks + 0) > 0';
    } elseif (strtolower($typeFilter) === 'ssl') {
      $whereParts[]          = '(type LIKE :type OR https = :https_true OR https = :https_one OR https = :https_int)';
      $params[':type']       = '%' . $typeFilter . '%';
      $params[':https_true'] = 'true';
      $params[':https_one']  = '1';
      $params[':https_int']  = 1;
    } else {
      $whereParts[]    = 'type LIKE :type';
      $params[':type'] = '%' . $typeFilter . '%';
    }
  }
  if (!empty($countryFilter)) {
    $whereParts[]       = 'country LIKE :country';
    $escCountry         = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $countryFilter);
    $params[':country'] = '%' . $escCountry . '%';
  }
  if (!empty($cityFilter)) {
    $whereParts[]    = 'city LIKE :city';
    $escCity         = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $cityFilter);
    $params[':city'] = '%' . $escCity . '%';
  }
  if (!empty($whereParts)) {
    $where = 'WHERE ' . implode(' AND ', $whereParts);
  }

  $orderSql = '';
  if (!empty($orderBy)) {
    // orderBy already sanitized earlier to `col DIR`
    $orderSql = 'ORDER BY ' . $orderBy;
  } else {
    // Default ordering when client didn't request one:
    // Order by a numeric timestamp where possible so RFC3339 datetimes
    // sort correctly across DB drivers. Rows with missing/invalid
    // `last_check` are treated as epoch 0 and therefore appear last
    // when sorting DESC (most-recent first).
    $drv = strtolower((string)$driver);
    if (strpos($drv, 'sqlite') !== false) {
      // SQLite: use strftime to get unix epoch seconds from ISO datetime
      $orderSql = "ORDER BY (CASE WHEN last_check IS NULL OR last_check = '' OR last_check = '-' THEN 0 ELSE COALESCE(strftime('%s', last_check), 0) END) DESC";
    } elseif (strpos($drv, 'mysql') !== false) {
      // MySQL: use UNIX_TIMESTAMP which accepts standard datetime formats
      $orderSql = "ORDER BY (CASE WHEN last_check IS NULL OR last_check = '' OR last_check = '-' THEN 0 ELSE COALESCE(UNIX_TIMESTAMP(last_check), 0) END) DESC";
    } else {
      // Fallback: prefer rows with a non-empty last_check, then sort by
      // the raw value. This may be less accurate for non-lexical drivers.
      $orderSql = "ORDER BY (CASE WHEN last_check IS NULL OR last_check = '' OR last_check = '-' THEN 0 ELSE 1 END) DESC, last_check DESC";
    }
  }

  $limitSql = '';
  if ($perPage !== null && $perPage > 0) {
    $offset = ($page - 1) * $perPage;
    // Use integer interpolation for LIMIT/OFFSET (safe after casting to int)
    $limitSql = 'LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset;
  }

  $sql         = 'SELECT * FROM proxies ' . $where . ' ' . $orderSql . ' ' . $limitSql;
  $sqlChecksum = md5($sql . json_encode($params) . $driver);
  $cacheFile   = get_project_root("tmp/proxies/{$sqlChecksum}.json");
  $response    = [
    'error'           => false,
    'draw'            => $draw,
    'recordsTotal'    => $recordsTotal,
    'recordsFiltered' => $recordsFiltered,
    'driver'          => $driver,
    'page'            => $page,
    'perPage'         => $perPage,
  ];

  $cacheTTL = 300; // Cache time-to-live in seconds (5 minutes)
  if (file_exists($cacheFile) && time() - filemtime($cacheFile) < $cacheTTL) {
    // Return cached response if query is the same and cache is less than cacheTTL seconds old
    $cachedResponse = json_decode(file_get_contents($cacheFile), true);
    if ($cachedResponse && !empty($cachedResponse['data'] ?? [])) {
      respond_json(array_merge($response, $cachedResponse, ['cached' => true]));
    }
  }

  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Add human-readable elapsed time for `last_check` as `timeAgo`.
  // `last_check` may be an RFC3339 datetime string, empty string, or null.
  if (!empty($rows)) {
    foreach ($rows as &$r) {
      $r['timeAgo'] = timeAgo((string)($r['last_check'] ?? ''));
    }
    unset($r);
  }

  $rows             = $rows ?: [];
  $response['data'] = array_values($rows);

  // Save the response to the cache file
  write_file($cacheFile, json_encode(array_merge($response, ['sql' => $sql, 'params' => $params]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

  respond_json($response);
} catch (Throwable $e) {
  respond_json(['error' => true, 'message' => $e->getMessage()]);
}
