<?php

// Server-side proxy list for DataTables
// Uses existing $proxy_db directly — does NOT instantiate or validate it.

declare(strict_types=1);

require_once __DIR__ . '/shared.php';

use PhpProxyHunter\Server;

Server::allowCors(true);

$refresh = refreshDbConnections();
/** @var \PhpProxyHunter\CoreDB $core_db */
$core_db = $refresh['core_db'];
/** @var \PhpProxyHunter\UserDB $user_db */
$user_db = $refresh['user_db'];
/** @var \PhpProxyHunter\ProxyDB $proxy_db */
$proxy_db = $refresh['proxy_db'];
/** @var \PhpProxyHunter\ActivityLog $log_db */
$log_db = $refresh['log_db'];

// helper to parse incoming payload if needed
$request = parseQueryOrPostBody();

header('Content-Type: application/json; charset=utf-8');

try {
  // DataTables draw param
  $draw = isset($request['draw']) ? (int)$request['draw'] : 0;

  // Paging: DataTables uses `start` and `length`
  $start  = isset($request['start']) ? max(0, (int)$request['start']) : 0;
  $length = isset($request['length']) ? (int)$request['length'] : 10;
  if ($length === -1) {
    // -1 means show all
    $length = null;
  }
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
      if ($col !== '') {
        $orderBy = $col . ' ' . $dir;
      }
    }
  }

  // Prefer using existing PDO instance from $proxy_db to reuse connections and helpers
  $pdo = $proxy_db->db->pdo;

  // total records
  $stmtTotal    = $pdo->query('SELECT COUNT(*) as cnt FROM proxies');
  $recordsTotal = (int)$stmtTotal->fetch(PDO::FETCH_ASSOC)['cnt'];

  // build filter
  $filter = [];
  if ($search !== '') {
    $filter['proxy'] = $search;
    // ProxyDB will perform prefix matching behavior
  }

  // recordsFiltered: if search present, count matching rows; else equals total
  if (!empty($filter)) {
    // search by proxy prefix
    $searchParam = $filter['proxy'] . '%';
    $stmt        = $pdo->prepare('SELECT COUNT(*) as cnt FROM proxies WHERE proxy LIKE :search');
    $stmt->execute([':search' => $searchParam]);
    $recordsFiltered = (int)$stmt->fetch(PDO::FETCH_ASSOC)['cnt'];
  } else {
    $recordsFiltered = $recordsTotal;
  }

  // Fetch rows with pagination and ordering
  // Build SQL query
  $where  = '';
  $params = [];
  if (!empty($filter)) {
    $where             = 'WHERE proxy LIKE :search';
    $params[':search'] = $filter['proxy'] . '%';
  }

  $orderSql = '';
  if (!empty($orderBy)) {
    // orderBy already sanitized earlier to `col DIR`
    $orderSql = 'ORDER BY ' . $orderBy;
  }

  $limitSql = '';
  if ($perPage !== null && $perPage > 0) {
    $offset = ($page - 1) * $perPage;
    // Use integer interpolation for LIMIT/OFFSET (safe after casting to int)
    $limitSql = 'LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset;
  }

  $sql  = 'SELECT * FROM proxies ' . $where . ' ' . $orderSql . ' ' . $limitSql;
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $rows = $rows ?: [];

  $response = [
    'draw'            => $draw,
    'recordsTotal'    => $recordsTotal,
    'recordsFiltered' => $recordsFiltered,
    'data'            => array_values($rows),
  ];

  echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => $e->getMessage()]);
}
