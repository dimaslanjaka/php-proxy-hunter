<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/hashers/CustomPasswordHasher.php';

$password = "my_secure_password";
$salt = CustomPasswordHasher::getSalt();
$encoded = CustomPasswordHasher::hash($password);
$isPhpValid = CustomPasswordHasher::verify($password, $encoded);
$from_py = "d2db5f1a1c8658d87a0e696ca24bd86fba0c1ed43646a55649b714b8b9ff6b0c$558b06a41620e188";
$isValid = CustomPasswordHasher::verify($password, $from_py);

var_dump($salt, $encoded, $isPhpValid, $isValid);
