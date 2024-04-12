<?php

// safe embbeder

// Allow from any origin
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");
header('Content-Type: text/plain; charset=utf-8');

if (isset($_GET['filename'])) {
  $file = __DIR__ . "/" . $_GET['filename'];
  if (file_exists($file)) {
    include $file;
  }
}
