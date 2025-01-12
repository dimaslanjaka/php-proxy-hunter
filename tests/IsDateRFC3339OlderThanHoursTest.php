<?php

require_once __DIR__ . '/../func.php';

use PHPUnit\Framework\TestCase;

// phpunit --verbose tests/IsDateRFC3339OlderThanHoursTest.php

class IsDateRFC3339OlderThanHoursTest extends TestCase
{
  public function testIsDateRFC3339OlderThanHoursTrue()
  {
    $this->assertTrue(isDateRFC3339OlderThanHours('2024-09-30T18:44:39+0700', 5));
  }

  public function testIsDateRFC3339OlderThanHoursFalse()
  {
    $minutesAgo = 10;

    // Create a DateTime object for the current time
    $date = new DateTime();

    // Subtract the specified number of minutes
    $date->modify("-{$minutesAgo} minutes");

    // Format the date as RFC 3339
    $rfc3339DateAgo = $date->format(DATE_RFC3339);

    $this->assertFalse(isDateRFC3339OlderThanHours($rfc3339DateAgo, 5));
  }
}
