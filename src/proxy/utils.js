import axios from 'axios';
import net from 'net';
import { isValidIp, isValidPort } from './validator.js';

const Socket = net.Socket;

// nodejs - error self signed certificate in certificate chain
// https://stackoverflow.com/a/73110972/6404439
// process.env["NODE_TLS_REJECT_UNAUTHORIZED"] = 1;

/**
 * Fetches the device public IP address of the client by trying multiple services.
 * @returns {Promise<string>} A promise that resolves to the public IP address as a string.
 * @throws {Error} Throws an error if none of the services return a valid IP address.
 */
export async function getPublicIP() {
  const urls = ['https://api.ipify.org?format=json', 'https://ifconfig.me/all.json', 'https://ipinfo.io/json'];

  for (const url of urls) {
    try {
      const response = await axios.get(url);
      let ip;

      // Determine which field contains the IP address based on the URL
      switch (url) {
        case 'https://api.ipify.org?format=json':
          ip = response.data.ip;
          break;
        case 'https://ifconfig.me/all.json':
          ip = response.data.ip_addr;
          break;
        case 'https://ipinfo.io/json':
          ip = response.data.ip;
          break;
        default:
          continue; // Skip unknown URLs
      }

      // Validate IP address format
      if (isValidIp(ip)) {
        return ip;
      }
    } catch (error) {
      console.error(`Error fetching IP address from ${url}:`, error);
      // Continue to the next URL if there is an error
    }
  }

  throw new Error('Unable to fetch a valid public IP address from any of the services.');
}

export class CheckerResult {
  result = false;
  https = false;
  /**
   * @type {string|null} error message
   */
  error = null;
  /**
   *
   * @param {boolean} result
   * @param {boolean} https
   * @param {Partial<string>} error
   */
  constructor(result, https, error = null) {
    this.result = result;
    this.https = https;
    this.error = error;
  }
}

/**
 * Checks if a given IP address and port are open.
 *
 * @param {string} proxy - The IP:PORT combination to check (e.g., '192.168.1.1:80').
 * @param {number} timeout - The timeout in milliseconds before failing the connection.
 * @returns {Promise<boolean>} - A promise that resolves to true if the port is open, otherwise false.
 */
export function isPortOpen(proxy, timeout = 60 * 1000) {
  const [ip, portStr] = proxy.split(':');
  const port = Number(portStr);
  if (!isValidPort(port)) return false;

  return new Promise((resolve) => {
    const socket = new Socket();

    socket.setTimeout(timeout);

    socket.on('connect', () => {
      // console.log(proxy, "port open");
      socket.destroy();
      resolve(true); // Port is open
    });

    socket.on('timeout', () => {
      // console.log(proxy, "open port checker failed [timeout]");
      socket.destroy();
      resolve(false); // Timeout reached
    });

    socket.on('error', () => {
      // console.log(proxy, "port closed");
      resolve(false); // Error occurred (port is closed)
    });

    socket.connect(port, ip);
  });
}

export default { CheckerResult, getPublicIP, isPortOpen };
