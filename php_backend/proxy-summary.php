<?php

// Server-side proxy summary counters
// Returns proxy statistics: total, working, private, https, untested, dead

require_once __DIR__ . '/shared.php';

use PhpProxyHunter\Server;

// Allow CORS
Server::allowCors(true);

// Only allow if captcha passed
if (empty($_SESSION['captcha'])) {
  respond_json(['error' => true, 'message' => 'Captcha not verified'], 403);
}

$refresh = refreshDbConnections();
/** @var \PhpProxyHunter\ProxyDB $proxy_db */
$proxy_db = $refresh['proxy_db'];

header('Content-Type: application/json; charset=utf-8');

try {
  $pdo = $proxy_db->db->pdo;

  $stmtCountries = $pdo->query("SELECT DISTINCT country FROM proxies
            WHERE country IS NOT NULL
              AND TRIM(country) <> ''
              AND TRIM(country) <> '-'
              AND UPPER(TRIM(country)) <> 'N/A'
            ORDER BY country");
  $countries = $stmtCountries->fetchAll(PDO::FETCH_COLUMN);

  $citiesSql = "SELECT DISTINCT city FROM proxies
            WHERE city IS NOT NULL
              AND TRIM(city) <> ''
              AND TRIM(city) <> '-'
              AND UPPER(TRIM(city)) <> 'N/A'";
  $citiesSql .= ' ORDER BY city';
  $stmtCities = $pdo->prepare($citiesSql);
  $stmtCities->execute();
  $cities = $stmtCities->fetchAll(PDO::FETCH_COLUMN);

  $response = [
    'error'           => false,
    'countries'       => $countries,
    'cities'          => $cities,
    'counter_proxies' => [
      'total_proxies'    => $proxy_db->countAllProxies(),
      'working_proxies'  => $proxy_db->countWorkingProxies(),
      'private_proxies'  => $proxy_db->countPrivateProxies(),
      'https_proxies'    => $proxy_db->countHttpsProxies(true),
      'untested_proxies' => $proxy_db->countUntestedProxies(),
      'dead_proxies'     => $proxy_db->countDeadProxies(),
    ],
  ];

  respond_json($response);
} catch (Throwable $e) {
  respond_json(['error' => $e->getMessage()], 500);
}
