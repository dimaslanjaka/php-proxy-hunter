<?php

declare(strict_types=1);

/**
 * Send a JSON response and terminate the script.
 *
 * Sets the Content-Type header to "application/json; charset=utf-8" if no headers
 * have been sent yet, encodes the provided array to JSON using
 * JSON_UNESCAPED_UNICODE and JSON_UNESCAPED_SLASHES, outputs it and exits.
 *
 * @param array<string,mixed> $data The data to encode as JSON.
 *
 * @return void
 */
function respond_json(array $data): void {
  if (headers_sent() === false) {
    header('Content-Type: application/json; charset=utf-8');
  }

  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit(0);
}

/**
 * Send a plain text response and terminate the script.
 *
 * Sets the Content-Type header to "text/plain; charset=utf-8" if no headers
 * have been sent yet, outputs the provided text and exits.
 *
 * @param string $text The plain text to send in the response body.
 *
 * @return void
 */
function respond_text(string $text): void {
  if (headers_sent() === false) {
    header('Content-Type: text/plain; charset=utf-8');
  }

  echo $text;
  exit(0);
}

/**
 * Send an HTML response and terminate the script.
 *
 * Sets the Content-Type header to "text/html; charset=utf-8" if no headers
 * have been sent yet, outputs the provided HTML and exits.
 *
 * @param string $html The HTML content to send in the response body.
 *
 * @return void
 */
function respond_html(string $html): void {
  if (headers_sent() === false) {
    header('Content-Type: text/html; charset=utf-8');
  }

  echo $html;
  exit(0);
}
