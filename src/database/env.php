<?php

/**
 * Retrieves the list of admin email addresses from the environment variable 'ADMIN_EMAILS'.
 *
 * @return string[] Array of trimmed admin email addresses.
 */
function getAdminEmails(): array
{
  $email       = isset($_ENV['ADMIN_EMAILS']) ? $_ENV['ADMIN_EMAILS'] : getenv('ADMIN_EMAILS');
  $adminEmails = $email ? explode(',', $email) : [];
  return array_map('trim', $adminEmails);
}
