<?php

/**
 * Check if a file was created a certain number of hours ago.
 *
 * @param string $filePath The path to the file.
 * @param int $hours The number of hours ago to check against.
 * @return bool True if the file was created more than the specified number of hours ago, otherwise false.
 */
function isFileCreatedMoreThanHours(string $filePath, int $hours): bool {
  // Check if the file exists
  if (!file_exists($filePath)) {
    return false;
  }

  // Get the file creation time
  $creationTime = filectime($filePath);

  // Calculate the time difference in seconds
  $differenceInSeconds = time() - $creationTime;

  // Calculate the time threshold in seconds
  $thresholdInSeconds = $hours * 60 * 60;

  // Check if the file was created more than the specified time frame
  return $differenceInSeconds > $thresholdInSeconds;
}
