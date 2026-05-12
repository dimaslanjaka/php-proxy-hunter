/**
 * Extracts IP:PORT pairs from a given input string.
 *
 * @param input - The input string containing IP:PORT pairs.
 * @returns An array of IP:PORT pairs found in the input string.
 */
export const extractIpPortPairs = function (input: string): string[] {
  if (!input) return [];
  const regex = /(?:[0-9]{1,3}\.){3}[0-9]{1,3}:[0-9]{1,5}/g;
  return input.match(regex) || [];
};
