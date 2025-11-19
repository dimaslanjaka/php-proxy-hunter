/**
 * Converts a given date string to a human-readable "time ago" format.
 * @param {string} dateString - The date string to be converted.
 * @param {boolean} [simple=false] - When true, shorten the output: for day(s) hide minutes and seconds; for hour(s) hide seconds only.
 * @param {boolean} [unitOnly=false] - When true, use short unit labels (d/h/m/s) instead of full words.
 * @returns {string} The time ago format of the provided date string.
 */
export function timeAgo(dateString, simple = false, unitOnly = false) {
  // Convert the provided date string to a Date object
  const date = new Date(dateString);

  // return invalid date to original string
  if (isNaN(date.getTime())) return dateString;

  // Get the current time
  const now = new Date();

  // Calculate the time difference in milliseconds
  const difference = now.getTime() - date.getTime();

  // Convert milliseconds to seconds, minutes, hours, and days
  const seconds = Math.floor(difference / 1000);
  const minutes = Math.floor(seconds / 60);
  const hours = Math.floor(minutes / 60);
  const days = Math.floor(hours / 24);

  // Calculate remaining hours, minutes, and seconds
  const remainingHours = hours % 24;
  const remainingMinutes = minutes % 60;
  const remainingSeconds = seconds % 60;

  // Helper to format unit with optional short label
  const fmt = (value, full, short) => {
    if (unitOnly) {
      return value + short + ' ';
    }
    return value + ' ' + full + (value === 1 ? '' : 's') + ' ';
  };

  // Construct the ago time string
  let agoTime = '';

  // If we have at least 1 day, always show days.
  // In simple mode, show days and hours but hide minutes and seconds.
  if (days > 0) {
    agoTime += fmt(days, 'day', 'd');
    if (remainingHours > 0) {
      agoTime += fmt(remainingHours, 'hour', 'h');
    }
    if (simple) {
      agoTime += 'ago';
      return agoTime;
    }
  }

  // If there are hours and no days, show hours.
  if (days === 0 && remainingHours > 0) {
    agoTime += fmt(remainingHours, 'hour', 'h');
  }

  // Minutes: include when not in simple+days mode. For simple with hours (and no days), minutes still show.
  if (remainingMinutes > 0 && !(simple && days > 0)) {
    agoTime += fmt(remainingMinutes, 'minute', 'm');
    // If minutes are the top unit (no hours and no days), show seconds as well
    if (days === 0 && remainingHours === 0 && remainingSeconds > 0) {
      agoTime += fmt(remainingSeconds, 'second', 's');
    }
  }

  // Seconds-only: include when seconds > 0 and there are no larger units.
  if (days === 0 && remainingHours === 0 && remainingMinutes === 0 && remainingSeconds > 0) {
    agoTime += fmt(remainingSeconds, 'second', 's');
  }

  // Append "ago" to the ago time string
  agoTime += 'ago';

  return agoTime;
}
