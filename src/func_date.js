import { format, isAfter, isBefore, parseISO, subHours } from 'date-fns';
import { format as formatZonedTime } from 'date-fns-tz';
import moment from 'moment-timezone';

/**
 * Checks if the current date and time are more than the given date and time in RFC 3339 format.
 * @param {string} givenDateTimeStr - Date and time in RFC 3339 format
 * @returns {boolean | null} True if current date and time are more than the given date and time, otherwise false
 */
export function isCurrentTimeMoreThanRFC3339(givenDateTimeStr) {
  try {
    const givenDateTime = parseISO(givenDateTimeStr);
    const currentDateTime = new Date();

    return isAfter(currentDateTime, givenDateTime);
  } catch (e) {
    logError(`Error parsing date: ${e.message}`);
    return null;
  }
}

/**
 * Check if the given RFC 3339 timestamp is older than a specified number of hours.
 * @param {string} dateStr - The RFC 3339 timestamp string with timezone offset.
 * @param {number} [hours=1] - The number of hours to check against.
 * @returns {boolean} True if the timestamp is older than the specified number of hours, False otherwise.
 */
export function isDateRFC3339OlderThan(dateStr, hours = 1) {
  const now = new Date();
  const timeThreshold = subHours(now, hours);

  try {
    let timestampDt = parseISO(dateStr);

    // If the date string does not have a timezone, assume it's UTC
    if (!dateStr.includes('Z') && !dateStr.includes('+')) {
      timestampDt = new Date(timestampDt.getTime() - timestampDt.getTimezoneOffset() * 60000);
    }

    return isBefore(timestampDt, timeThreshold);
  } catch (_e) {
    logError(`Invalid timestamp format: ${dateStr}. Expected RFC3339 format.`);
    return false;
  }
}

/**
 * Returns the current date and time formatted according to RFC 3339.
 * @param {boolean} [useUtc=false] - If True, the time will be in UTC. If False, the local timezone will be used.
 * @returns {string} Current date and time in RFC 3339 format.
 */
export function getCurrentRFC3339Time(useUtc = false) {
  const now = new Date();
  return useUtc
    ? formatZonedTime(now, "yyyy-MM-dd'T'HH:mm:ssX", { timeZone: 'UTC' })
    : formatZonedTime(now, "yyyy-MM-dd'T'HH:mm:ssXXX", { timeZone: moment.tz.guess() });
}

/**
 * Convert RFC 3339 string to human readable format
 * @param {string} rfc3339Date - RFC 3339 formatted date string
 * @returns {string} Human readable date and time
 */
export function convertRFC3339ToHumanReadable(rfc3339Date) {
  if (rfc3339Date.endsWith('Z')) {
    rfc3339Date = rfc3339Date.slice(0, -1);
  }

  const dt = parseISO(rfc3339Date);
  return format(dt, 'yyyy-MM-dd HH:mm:ss');
}

/**
 * Get system timezone name.
 * @returns {string} Timezone name
 */
export function getSystemTimezone() {
  return moment.tz.guess() || 'UTC';
}

/**
 * Check if the given date string is more than the specified hours ago.
 * @param {string | null} dateString - The date string in RFC3339 format (e.g., "2024-05-06T12:34:56+00:00").
 * @param {number} hours - The number of hours.
 * @returns {boolean | null} True if the date is more than the specified hours ago, False otherwise.
 */
export function isDateRFC3339HourMoreThan(dateString, hours) {
  if (!dateString) return null;

  try {
    const dateTime = parseISO(dateString);
    return subHours(new Date(), hours) >= dateTime;
  } catch (_e) {
    logError(`Invalid date string format: ${dateString}. Expected RFC3339 format.`);
    return null;
  }
}

/**
 * Log error messages.
 * @param {string} message - Error message
 */
function logError(message) {
  console.error(`Error: ${message}`);
}
