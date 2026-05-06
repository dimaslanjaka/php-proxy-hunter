<?php

require_once __DIR__ . '/../func.php';

try {
  runShellCommandLive('ping google.com'); // Example command
} catch (Exception $e) {
  echo 'Error: ' . $e->getMessage();
}
