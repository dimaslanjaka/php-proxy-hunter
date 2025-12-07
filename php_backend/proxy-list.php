<?php

// Server-side proxy list for DataTables
// Uses existing $proxy_db directly — does NOT instantiate or validate it.

declare(strict_types=1);

require_once __DIR__ . '/shared.php';

use PhpProxyHunter\Server;

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

  // Optional status filter
  $statusFilter = '';
  if (isset($request['status'])) {
    $statusFilter = trim((string)$request['status']);
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

  // recordsFiltered: count matching rows according to active filters
  $recordsFiltered = $recordsTotal;
  $whereParts      = [];
  $countParams     = [];
  if ($search !== '') {
    $whereParts[]           = 'proxy LIKE :search';
    $countParams[':search'] = $search . '%';
  }
  if ($statusFilter !== '') {
    $whereParts[]           = 'status = :status';
    $countParams[':status'] = $statusFilter;
  }
  if (!empty($whereParts)) {
    $countSql = 'SELECT COUNT(*) as cnt FROM proxies WHERE ' . implode(' AND ', $whereParts);
    $stmt     = $pdo->prepare($countSql);
    $stmt->execute($countParams);
    $recordsFiltered = (int)$stmt->fetch(PDO::FETCH_ASSOC)['cnt'];
  }

  // Fetch rows with pagination and ordering
  // Build SQL query
  $where  = '';
  $params = [];
  if ($search !== '') {
    $whereParts[]      = 'proxy LIKE :search';
    $params[':search'] = $search . '%';
  }
  if ($statusFilter !== '') {
    $whereParts[]      = 'status = :status';
    $params[':status'] = $statusFilter;
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
    // - Prefer rows with a valid last_check (non-empty and not '-')
    // - Then sort by last_check descending so most-recent checks appear first
    // Note: this assumes last_check is stored as an RFC3339/ISO datetime string
    $orderSql = "ORDER BY (CASE WHEN last_check IS NULL OR last_check = '' OR last_check = '-' THEN 0 ELSE 1 END) DESC, last_check DESC";
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
    'error'           => false,
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
