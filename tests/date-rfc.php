<?php

require_once __DIR__ . '/../func.php';

// should be true
var_dump(isDateRFC3339OlderThanHours('2024-09-30T18:44:39+0700'), 5);

// Number of minutes ago
$minutesAgo = 10;

// Create a DateTime object for the current time
$date = new DateTime();

// Subtract the specified number of minutes
$date->modify("-{$minutesAgo} minutes");

// Format the date as RFC 3339
$rfc3339Date_ago = $date->format(DATE_RFC3339);

// should be false
var_dump(isDateRFC3339OlderThanHours($rfc3339Date_ago), 5);
