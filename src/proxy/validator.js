/**
 * Validates a proxy string.
 *
 * @param {string|null} proxy - The proxy string to validate.
 * @param {boolean} [validateCredential=false] - Whether to validate credentials if present.
 * @returns {boolean} - True if the proxy is valid, False otherwise.
 */
export function isValidProxy(proxy, validateCredential = false) {
  if (!proxy) {
    return false;
  }

  // Handle credentials if present
  const hasCredential = proxy.includes('@');
  if (hasCredential) {
    try {
      let [proxyPart, credential] = proxy.trim().split('@', 2);
      proxy = proxyPart;

      let [username, password] = credential.trim().split(':');
      if (validateCredential && (!username || !password)) {
        return false;
      }
    } catch (_err) {
      return false; // Invalid credentials format
    }
  }

  // Extract IP address and port
  const parts = proxy.trim().split(':', 2);
  if (parts.length !== 2) {
    return false;
  }

  const [ip, port] = parts;

  // Validate IP address (using provided function)
  if (!isValidIp(ip) || !isValidPort(port)) return false;

  // Validate port number
  const portInt = parseInt(port, 10);
  if (isNaN(portInt) || portInt < 1 || portInt > 65535) {
    return false;
  }

  // Check if the proxy string length is appropriate (if applicable)
  const proxyLength = proxy.length;
  if (proxyLength < 7 || proxyLength > 21) {
    // Adjust based on valid range
    return false;
  }

  return true;
}

/**
 * Validates if the given IP address is in a valid format.
 * @param {string} ip The IP address to validate.
 * @returns {boolean} True if the IP address is valid, otherwise false.
 */
export function isValidIp(ip) {
  const ipPattern =
    /^(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/;
  return ipPattern.test(ip);
}

/**
 * Validates if the given port number is a valid integer within the valid range.
 *
 * @param {number|string} port - The port number to validate, can be a number or a string.
 * @returns {boolean} - Returns true if the port is valid, otherwise false.
 */
export function isValidPort(port) {
  const parsedPort = Number(port); // Parse the input as a number

  // Check if the parsed value is NaN or out of range
  return !isNaN(parsedPort) && parsedPort >= 0 && parsedPort <= 65535;
}
