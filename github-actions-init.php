<?php

require_once __DIR__ . '/func-proxy.php';

global $isCli;

use PhpProxyHunter\ProxyDB;

if (!$isCli) {
  exit('Only CLI allowed');
}

// List of required Composer packages
$requiredPackages = [
  'geoip2/geoip2',
  'annexare/countries-list'
];

// Function to check installed packages using composer show
function checkMissingPackages(array $requiredPackages): array
{
  $installedPackages = json_decode(shell_exec('composer show --format=json'), true)['installed'] ?? [];
  $installedNames = array_column($installedPackages, 'name');

  return array_filter($requiredPackages, function ($package) use ($installedNames) {
    return !in_array(strtolower($package), array_map('strtolower', $installedNames));
  });
}

// Install missing packages if any
$missingPackages = checkMissingPackages($requiredPackages);
if (!empty($missingPackages)) {
  echo "Missing packages detected: " . implode(', ', $missingPackages) . "\n";
  echo "Running composer install...\n";
  shell_exec('php composer.phar install --prefer-dist --no-progress');
} else {
  echo "All required packages are installed.\n";
}

// Update config/CLI.json
$configPath = __DIR__ . '/config/CLI.json';
$data = [
  "endpoint" => "https://www.example.com/",
  "headers" => [
    "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8",
    "Accept-Language: en-US,en;q=0.5",
    "Connection: keep-alive",
    "Host: www.example.com",
    "User-Agent" => "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:91.0) Gecko/20100101 Firefox/91.0"
  ],
  "type" => "http|socks4|socks5"
];
write_file($configPath, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

// Fetch and process working proxies
$db = new ProxyDB();
$proxyData = parse_working_proxies($db);

// Write proxy counts to GITHUB_OUTPUT if the environment variable exists
$githubOutputPath = getenv('GITHUB_OUTPUT');
if ($githubOutputPath) {
  $output = '';
  foreach ($proxyData['counter'] as $key => $value) {
    echo "total $key $value proxies" . PHP_EOL;
    $output .= "total_$key=$value\n";
  }

  file_put_contents($githubOutputPath, $output, FILE_APPEND);
}
