<?php

/**
 * Check if a given date string in RFC3339 format is older than the specified number of hours.
 *
 * @param string $dateString The date string in DATE_RFC3339 format.
 * @param int $hoursAgo The number of hours to compare against.
 * @return bool True if the date is older than the specified number of hours, false otherwise.
 */
function isDateRFC3339OlderThanHours(string $dateString, int $hoursAgo = 5): bool {
  try {
    // Create a DateTime object from the string
    $date = new DateTime($dateString);
  } catch (Exception $e) {
    // Handle exception if DateTime creation fails
    return false;
  }

  try {
    // Create a DateTime object representing the specified number of hours ago
    $hoursAgoDateTime = new DateTime();
    $hoursAgoDateTime->sub(new DateInterval('PT' . $hoursAgo . 'H'));
  } catch (Exception $e) {
    // Handle exception if DateTime creation fails
    return false;
  }

  // Compare the date with the specified number of hours ago
  return $date < $hoursAgoDateTime;
}
