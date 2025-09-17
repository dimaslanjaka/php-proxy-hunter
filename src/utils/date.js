/**
 * Converts a given date string to a human-readable "time ago" format.
 * @param {string} dateString - The date string to be converted.
 * @returns {string} The time ago format of the provided date string.
 */
export function timeAgo(dateString) {
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

  // Construct the ago time string
  let agoTime = '';
  if (days > 0) agoTime += days + ' day' + (days === 1 ? '' : 's') + ' ';
  if (remainingHours > 0) agoTime += remainingHours + ' hour' + (remainingHours === 1 ? '' : 's') + ' ';
  if (remainingMinutes > 0) agoTime += remainingMinutes + ' minute' + (remainingMinutes === 1 ? '' : 's') + ' ';
  if (remainingSeconds > 0) agoTime += remainingSeconds + ' second' + (remainingSeconds === 1 ? '' : 's') + ' ';

  // Append "ago" to the ago time string
  agoTime += 'ago';

  return agoTime;
}
