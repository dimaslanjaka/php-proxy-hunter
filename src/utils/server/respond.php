<?php

/**
 * Helper response functions in this file accept an optional integer $status
 * parameter (HTTP status code). The functions will set the response code via
 * http_response_code() and set an appropriate Content-Type header when headers
 * have not been sent yet. Defaults to 200 (OK).
 */

/**
 * Send a JSON response and terminate the script.
 *
 * Sets the Content-Type header to "application/json; charset=utf-8" if no headers
 * have been sent yet, encodes the provided array to JSON using
 * JSON_UNESCAPED_UNICODE and JSON_UNESCAPED_SLASHES, outputs it and exits.
 *
 * @param array<string,mixed> $data The data to encode as JSON.
 * @param int $status Optional HTTP status code to send (default 200).
 *
 * @return void
 */
function respond_json(array $data, int $status = 200): void {
  if (headers_sent() === false) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
  } else {
    // If headers already sent, attempt to set response code anyway.
    http_response_code($status);
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
 * @param int $status Optional HTTP status code to send (default 200).
 *
 * @return void
 */
function respond_text(string $text, int $status = 200): void {
  if (headers_sent() === false) {
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
  } else {
    http_response_code($status);
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
 * @param int $status Optional HTTP status code to send (default 200).
 *
 * @return void
 */
function respond_html(string $html, int $status = 200): void {
  if (headers_sent() === false) {
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
  } else {
    http_response_code($status);
  }

  echo $html;
  exit(0);
}
