/**
 * Extract IP addresses from a string.
 * @param str - The string to search.
 * @returns The list of IPv4 addresses found in the input string.
 */
export const findIPv4Addresses = function (str: string): string[] {
  const ipv4Pattern =
    /\b(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\b/g;
  return str.match(ipv4Pattern) || [];
};
