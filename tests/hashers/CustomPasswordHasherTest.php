<?php

require_once __DIR__ . '/../../src/hashers/CustomPasswordHasher.php';

$password = "my_secure_password";
$encoded = CustomPasswordHasher::hash($password);
$isPhpValid = CustomPasswordHasher::verify($password, $encoded);
$from_py = "b09067ff3bdbaf24c708b893499d9c783d425688dc91c185e15461035ad6f59b$44469f22d81a4d137a9772fe26a6b230";
$isValid = CustomPasswordHasher::verify($password, $from_py);

var_dump($password, $encoded, $isPhpValid, $isValid);
