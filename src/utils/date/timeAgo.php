<?php

/**
 * Converts a given date string to a human-readable "time ago" format.
 *
 * @param string  $dateString The date string to be converted.
 * @param bool    $simple     When true, shorten the output.
 * @param bool    $unitOnly   When true, use short unit labels.
 * @return string
 */
function timeAgo($dateString, $simple = false, $unitOnly = false) {
  // Try converting to DateTime
  try {
    $date = new DateTime($dateString);
  } catch (Exception $e) {
    return $dateString;
    // invalid date → return original
  }

  $now        = new DateTime();
  $difference = $now->getTimestamp() - $date->getTimestamp();

  if ($difference < 0) {
    $difference = 0;
  }
  // future-proofing

  // Convert
  $seconds = floor($difference);
  $minutes = floor($seconds / 60);
  $hours   = floor($minutes / 60);
  $days    = floor($hours / 24);

  $remainingHours   = $hours   % 24;
  $remainingMinutes = $minutes % 60;
  $remainingSeconds = $seconds % 60;

  // Formatter helper
  $fmt = function ($value, $full, $short) use ($unitOnly) {
    if ($unitOnly) {
      return $value . $short . ' ';
    }
    return $value . ' ' . $full . ($value === 1 ? '' : 's') . ' ';
  };

  $agoTime = '';

  // Days logic
  if ($days > 0) {
    $agoTime .= $fmt($days, 'day', 'd');

    if ($remainingHours > 0) {
      $agoTime .= $fmt($remainingHours, 'hour', 'h');
    }

    if ($simple) {
      return $agoTime . 'ago';
    }
  }

  // Hours (only if no days)
  if ($days === 0 && $remainingHours > 0) {
    $agoTime .= $fmt($remainingHours, 'hour', 'h');
  }

  // Minutes
  if ($remainingMinutes > 0 && !($simple && $days > 0)) {
    $agoTime .= $fmt($remainingMinutes, 'minute', 'm');

    // Include seconds if minutes are the top unit
    if ($days === 0 && $remainingHours === 0 && $remainingSeconds > 0) {
      $agoTime .= $fmt($remainingSeconds, 'second', 's');
    }
  }

  // Seconds-only
  if ($days === 0 && $remainingHours === 0 && $remainingMinutes === 0 && $remainingSeconds > 0) {
    $agoTime .= $fmt($remainingSeconds, 'second', 's');
  }

  $agoTime .= 'ago';

  return $agoTime;
}
