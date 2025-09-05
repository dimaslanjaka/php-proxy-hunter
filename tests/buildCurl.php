<?php

require __DIR__ . '/../func-proxy.php';

$headers = [
  'Content-Type: application/json',
];

$ch = buildCurl(
  null,
  'http',
  'https://jsonplaceholder.typicode.com/posts/1',
  $headers,
  null,
  null,
  'GET',
  null
);

$response = curl_exec($ch);
curl_close($ch);

echo 'GET Response: ' . $response . "\n";

$post_data = json_encode([
  'title'  => 'foo',
  'body'   => 'bar',
  'userId' => 1,
]);

$headers = [
  'Content-Type: application/json',
];

$ch = buildCurl(
  null,
  'http',
  'https://jsonplaceholder.typicode.com/posts',
  $headers,
  null,
  null,
  'POST',
  $post_data
);

$response = curl_exec($ch);
curl_close($ch);

echo 'POST JSON Response: ' . $response . "\n";

$post_data = http_build_query([
  'title'  => 'foo',
  'body'   => 'bar',
  'userId' => 1,
]);

$headers = [
  'Content-Type: application/x-www-form-urlencoded',
];

$ch = buildCurl(
  null,
  'http',
  'https://jsonplaceholder.typicode.com/posts',
  $headers,
  null,
  null,
  'POST',
  $post_data
);

$response = curl_exec($ch);
curl_close($ch);

echo 'POST URL Form Encoded Response: ' . $response . "\n";

$put_data = json_encode([
  'id'     => 1,
  'title'  => 'foo',
  'body'   => 'bar',
  'userId' => 1,
]);

$headers = [
  'Content-Type: application/json',
];

$ch = buildCurl(
  null,
  'http',
  'https://jsonplaceholder.typicode.com/posts/1',
  $headers,
  null,
  null,
  'PUT',
  $put_data
);

$response = curl_exec($ch);
curl_close($ch);

echo 'PUT Response: ' . $response . "\n";

$patch_data = json_encode([
  'title' => 'foo',
]);

$headers = [
  'Content-Type: application/json',
];

$ch = buildCurl(
  null,
  'http',
  'https://jsonplaceholder.typicode.com/posts/1',
  $headers,
  null,
  null,
  'PATCH',
  $patch_data
);

$response = curl_exec($ch);
curl_close($ch);

echo 'PATCH Response: ' . $response . "\n";
