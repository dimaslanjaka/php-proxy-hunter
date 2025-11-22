<?php

function isValidEmail($email) {
  // Check if email is not empty
  if (empty($email)) {
    return false;
  }

  $email = trim($email);

  // Validate email format
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    return false;
  }

  return true;
}
