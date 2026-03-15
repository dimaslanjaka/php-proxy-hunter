<?php

// Server-side proxy list grouped by unique IP for DataTables-style pagination.

declare(strict_types=1);

require_once __DIR__ . '/shared.php';

use PhpProxyHunter\Server;

/**
 * Extract IP and port from a proxy string.
 *
 * Supports common forms such as:
 * - ip:port
 * - http://ip:port
 * - ip:port@user:pass
 * - user:pass@ip:port
 *
 * @param string $proxyRaw
 * @return array{ip:string,port:string}|null
 */
function extractIpPort($proxyRaw)
{
  $proxy = trim((string)$proxyRaw);
  if ($proxy === '') {
    return null;
  }

  // Remove scheme if present.
  $proxy = preg_replace('/^[a-z][a-z0-9+\-.]*:\/\//i', '', $proxy);
  if (!is_string($proxy) || $proxy === '') {
    return null;
  }

  // Remove path/query fragment if present.
  $proxy = preg_split('/[\/?#]/', $proxy, 2)[0] ?? $proxy;
  if (!is_string($proxy) || $proxy === '') {
    return null;
  }

  // Handle auth separators. Keep the segment that ends in :port when possible.
  if (strpos($proxy, '@') !== false) {
    $parts = explode('@', $proxy);
    if (!empty($parts)) {
      $candidate = end($parts);
      if (is_string($candidate) && preg_match('/:\d{1,5}$/', $candidate)) {
        $proxy = $candidate;
      } else {
        $first = $parts[0] ?? '';
        if (is_string($first) && preg_match('/:\d{1,5}$/', $first)) {
          $proxy = $first;
        }
      }
    }
  }

  $lastColonPos = strrpos($proxy, ':');
  if ($lastColonPos === false) {
    return null;
  }

  $ip   = trim(substr($proxy, 0, $lastColonPos));
  $port = trim(substr($proxy, $lastColonPos + 1));

  // Remove IPv6 brackets if present.
  if (strlen($ip) >= 2 && $ip[0] === '[' && substr($ip, -1) === ']') {
    $ip = substr($ip, 1, -1);
  }

  if ($ip === '' || $port === '' || !ctype_digit($port)) {
    return null;
  }

  return ['ip' => $ip, 'port' => $port];
}

/**
 * Build grouped unique-IP rows from raw proxy rows.
 *
 * @param array<int,array<string,mixed>> $rows
 * @return array<int,array<string,mixed>>
 */
function buildUniqueIpRows($rows)
{
  $grouped = [];

  foreach ($rows as $row) {
    $parsed = extractIpPort((string)($row['proxy'] ?? ''));
    if ($parsed === null) {
      continue;
    }

    $ip   = $parsed['ip'];
    $port = $parsed['port'];

    if (!isset($grouped[$ip])) {
      $grouped[$ip] = [
        'ip'          => $ip,
        'ports_map'   => [],
        'status_map'  => [],
        'type_map'    => [],
        'proxy_count' => 0,
        'country'     => '-',
        'city'        => '-',
        'last_check'  => '-',
      ];
    }

    $grouped[$ip]['proxy_count']++;
    $grouped[$ip]['ports_map'][$port] = true;

    $status = trim((string)($row['status'] ?? ''));
    if ($status !== '') {
      $grouped[$ip]['status_map'][$status] = true;
    }

    $typeStr = trim((string)($row['type'] ?? ''));
    if ($typeStr !== '') {
      $types = explode('-', $typeStr);
      foreach ($types as $t) {
        $t = trim($t);
        if ($t !== '') {
          $grouped[$ip]['type_map'][$t] = true;
        }
      }
    }

    $country = trim((string)($row['country'] ?? ''));
    if ($grouped[$ip]['country'] === '-' && $country !== '' && $country !== '-' && $country !== 'N/A') {
      $grouped[$ip]['country'] = $country;
    }

    $city = trim((string)($row['city'] ?? ''));
    if ($grouped[$ip]['city'] === '-' && $city !== '' && $city !== '-' && $city !== 'N/A') {
      $grouped[$ip]['city'] = $city;
    }

    $lastCheck = trim((string)($row['last_check'] ?? ''));
    if ($lastCheck !== '' && $lastCheck !== '-') {
      if ($grouped[$ip]['last_check'] === '-' || $lastCheck > $grouped[$ip]['last_check']) {
        $grouped[$ip]['last_check'] = $lastCheck;
      }
    }
  }

  $result = [];
  foreach ($grouped as $item) {
    $ports = array_keys($item['ports_map']);
    usort($ports, function ($a, $b) {
      return (int)$a <=> (int)$b;
    });

    $statuses = array_keys($item['status_map']);
    sort($statuses, SORT_NATURAL | SORT_FLAG_CASE);

    $types = array_keys($item['type_map']);
    sort($types, SORT_NATURAL | SORT_FLAG_CASE);

    $result[] = [
      'ip'          => $item['ip'],
      'ports'       => $ports,
      'proxy_count' => (int)$item['proxy_count'],
      'statuses'    => $statuses,
      'types'       => $types,
      'country'     => $item['country'],
      'city'        => $item['city'],
      'last_check'  => $item['last_check'],
      'proxy_list'  => array_map(function ($p) use ($item) {
        return $item['ip'] . ':' . $p;
      }, $ports),
    ];
  }

  usort($result, function ($a, $b) {
    $aLast = (string)($a['last_check'] ?? '-');
    $bLast = (string)($b['last_check'] ?? '-');

    $aHas = $aLast !== '-' && $aLast !== '';
    $bHas = $bLast !== '-' && $bLast !== '';

    if ($aHas !== $bHas) {
      return $aHas ? -1 : 1;
    }

    if ($aHas && $bHas && $aLast !== $bLast) {
      return strcmp($bLast, $aLast);
    }

    return strcmp((string)$a['ip'], (string)$b['ip']);
  });

  return $result;
}

/**
 * Count distinct IPs by streaming a PDO statement row-by-row.
 * The statement must have at least a `proxy` column selected.
 * This avoids loading all rows into memory at once.
 *
 * @param PDOStatement $stmt Already-executed statement
 * @return int
 */
function countUniqueIpsStreamed(PDOStatement $stmt): int
{
  $seen = [];
  while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
    $parsed = extractIpPort((string)($row['proxy'] ?? ''));
    if ($parsed !== null) {
      $seen[$parsed['ip']] = true;
    }
  }
  $stmt->closeCursor();
  return count($seen);
}

/**
 * Build grouped unique-IP rows from a streaming PDO statement.
 * Rows are read one at a time so the full result set never lives in memory.
 *
 * @param PDOStatement $stmt Already-executed statement
 * @return array<int,array<string,mixed>>
 */
function buildUniqueIpRowsStreamed(PDOStatement $stmt): array
{
  $grouped = [];

  while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
    $parsed = extractIpPort((string)($row['proxy'] ?? ''));
    if ($parsed === null) {
      continue;
    }

    $ip   = $parsed['ip'];
    $port = $parsed['port'];

    if (!isset($grouped[$ip])) {
      $grouped[$ip] = [
        'ip'          => $ip,
        'ports_map'   => [],
        'status_map'  => [],
        'type_map'    => [],
        'proxy_count' => 0,
        'country'     => '-',
        'city'        => '-',
        'last_check'  => '-',
      ];
    }

    $grouped[$ip]['proxy_count']++;
    $grouped[$ip]['ports_map'][$port] = true;

    $status = trim((string)($row['status'] ?? ''));
    if ($status !== '') {
      $grouped[$ip]['status_map'][$status] = true;
    }

    $typeStr = trim((string)($row['type'] ?? ''));
    if ($typeStr !== '') {
      foreach (explode('-', $typeStr) as $t) {
        $t = trim($t);
        if ($t !== '') {
          $grouped[$ip]['type_map'][$t] = true;
        }
      }
    }

    $country = trim((string)($row['country'] ?? ''));
    if ($grouped[$ip]['country'] === '-' && $country !== '' && $country !== '-' && $country !== 'N/A') {
      $grouped[$ip]['country'] = $country;
    }

    $city = trim((string)($row['city'] ?? ''));
    if ($grouped[$ip]['city'] === '-' && $city !== '' && $city !== '-' && $city !== 'N/A') {
      $grouped[$ip]['city'] = $city;
    }

    $lastCheck = trim((string)($row['last_check'] ?? ''));
    if ($lastCheck !== '' && $lastCheck !== '-') {
      if ($grouped[$ip]['last_check'] === '-' || $lastCheck > $grouped[$ip]['last_check']) {
        $grouped[$ip]['last_check'] = $lastCheck;
      }
    }
  }

  $stmt->closeCursor();

  // Finalise: convert internal maps to clean output arrays.
  $result = [];
  foreach ($grouped as $item) {
    $ports = array_keys($item['ports_map']);
    usort($ports, function ($a, $b) {
      return (int)$a <=> (int)$b;
    });

    $statuses = array_keys($item['status_map']);
    sort($statuses, SORT_NATURAL | SORT_FLAG_CASE);

    $types = array_keys($item['type_map']);
    sort($types, SORT_NATURAL | SORT_FLAG_CASE);

    $result[] = [
      'ip'          => $item['ip'],
      'ports'       => $ports,
      'proxy_count' => (int)$item['proxy_count'],
      'statuses'    => $statuses,
      'types'       => $types,
      'country'     => $item['country'],
      'city'        => $item['city'],
      'last_check'  => $item['last_check'],
      'proxy_list'  => array_map(function ($p) use ($item) {
        return $item['ip'] . ':' . $p;
      }, $ports),
    ];
  }

  usort($result, function ($a, $b) {
    $aLast = (string)($a['last_check'] ?? '-');
    $bLast = (string)($b['last_check'] ?? '-');
    $aHas  = $aLast !== '-' && $aLast !== '';
    $bHas  = $bLast !== '-' && $bLast !== '';
    if ($aHas !== $bHas) {
      return $aHas ? -1 : 1;
    }
    if ($aHas && $bHas && $aLast !== $bLast) {
      return strcmp($bLast, $aLast);
    }
    return strcmp((string)$a['ip'], (string)$b['ip']);
  });

  return $result;
}

// Allow CORS
Server::allowCors(true);

// Only allow if captcha passed
if (empty($_SESSION['captcha'])) {
  http_response_code(403);
  echo json_encode(['error' => true, 'message' => 'Captcha not verified']);
  exit;
}

$refresh = refreshDbConnections();
/** @var \PhpProxyHunter\CoreDB $core_db */
$core_db = $refresh['core_db'];
/** @var \PhpProxyHunter\ProxyDB $proxy_db */
$proxy_db = $refresh['proxy_db'];

$request = parseQueryOrPostBody();

header('Content-Type: application/json; charset=utf-8');

try {
  $draw = isset($request['draw']) ? (int)$request['draw'] : 0;

  $start        = isset($request['start']) ? max(0, (int)$request['start']) : 0;
  $length       = isset($request['length']) ? (int)$request['length'] : 10;
  $MAX_PER_PAGE = 500;
  if ($length === -1) {
    $length = $MAX_PER_PAGE;
  }
  $length  = max(1, min($length, $MAX_PER_PAGE));
  $page    = (int)floor($start / $length) + 1;
  $perPage = $length;

  $search = '';
  if (isset($request['search']) && is_array($request['search']) && isset($request['search']['value'])) {
    $search = trim((string)$request['search']['value']);
  } elseif (isset($request['q'])) {
    $search = trim((string)$request['q']);
  } elseif (isset($request['proxy'])) {
    $search = trim((string)$request['proxy']);
  }

  $statusFilter = '';
  if (isset($request['status'])) {
    $statusFilter = trim((string)$request['status']);
  }

  $typeFilter = '';
  if (isset($request['type'])) {
    $typeFilter = trim((string)$request['type']);
  }

  $countryFilter = '';
  if (isset($request['country'])) {
    $countryFilter = trim((string)$request['country']);
  }

  $cityFilter = '';
  if (isset($request['city'])) {
    $cityFilter = trim((string)$request['city']);
  }

  $pdo    = $proxy_db->db->pdo;
  $driver = $core_db->driver;

  if (isset($request['get_statuses']) && $request['get_statuses']) {
    $stmt     = $pdo->query('SELECT DISTINCT status FROM proxies ORDER BY status');
    $statuses = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode(['statuses' => $statuses], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }

  // Count total unique IPs by streaming rows one-by-one — avoids loading the
  // entire table into PHP memory at once.
  $stmtAll      = $pdo->query('SELECT proxy FROM proxies');
  $recordsTotal = countUniqueIpsStreamed($stmtAll);
  unset($stmtAll);

  // Filtered row set
  $whereParts = [];
  $params     = [];

  if ($search !== '') {
    $esc               = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
    $whereParts[]      = '(proxy LIKE :search OR country LIKE :search OR city LIKE :search)';
    $params[':search'] = '%' . $esc . '%';
  }

  if ($statusFilter !== '') {
    $whereParts[]      = 'status = :status';
    $params[':status'] = $statusFilter;
  }

  if ($countryFilter !== '') {
    $escCountry         = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $countryFilter);
    $whereParts[]       = 'country LIKE :country';
    $params[':country'] = '%' . $escCountry . '%';
  }

  if ($cityFilter !== '') {
    $escCity         = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $cityFilter);
    $whereParts[]    = 'city LIKE :city';
    $params[':city'] = '%' . $escCity . '%';
  }

  if ($typeFilter !== '') {
    if (strtolower($typeFilter) === 'ssl') {
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

  $whereSql = '';
  if (!empty($whereParts)) {
    $whereSql = ' WHERE ' . implode(' AND ', $whereParts);
  }

  $sql  = 'SELECT proxy, status, type, country, city, last_check FROM proxies' . $whereSql;
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);

  // Stream rows into the grouped structure — never holds all raw rows in memory.
  $grouped = buildUniqueIpRowsStreamed($stmt);
  unset($stmt);
  $recordsFiltered = count($grouped);

  $offset    = ($page - 1) * $perPage;
  $pagedRows = array_slice($grouped, $offset, $perPage);

  $response = [
    'error'           => false,
    'draw'            => $draw,
    'recordsTotal'    => $recordsTotal,
    'recordsFiltered' => $recordsFiltered,
    'data'            => array_values($pagedRows),
    'driver'          => $driver,
    'page'            => $page,
    'perPage'         => $perPage,
  ];

  echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => $e->getMessage()]);
}
