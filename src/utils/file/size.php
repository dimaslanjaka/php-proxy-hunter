<?php

/**
 * Return human readable filesize string.
 *
 * @param int|float $bytes
 * @param int $decimals
 * @return string
 */
function human_filesize($bytes, $decimals = 2)
{
  $bytes = (float)$bytes;
  if ($bytes <= 0) {
    return '0 B';
  }
  $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
  $i     = 0;
  while ($bytes >= 1024 && $i < count($units) - 1) {
    $bytes /= 1024;
    $i++;
  }
  if ($i === 0) {
    return sprintf('%d %s', (int)$bytes, $units[$i]);
  }
  return sprintf('%.' . (int)$decimals . 'f %s', $bytes, $units[$i]);
}
