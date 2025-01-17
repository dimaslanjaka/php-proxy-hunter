import * as axios from 'axios';
import fs from 'fs-extra';
import { pathToFileURL } from 'node:url';
import pino from 'pino';
import { writefile } from 'sbg-utility';
import path from 'upath';
import { PROJECT_DIR } from '../.env.mjs';

export const FOLDER_CONFIG = './baileys_auth_info';
export const FOLDER_LOG = './baileys_auth_info/log';

/**
 * Constructs a file path relative to the project's directory.
 *
 * @param {...string} files - A list of path segments to append to the project directory.
 * @returns {string} The absolute path to the desired file or directory.
 */
export function getWhatsappFile(...files: string[]): string {
  const joinedPath = files.join(path.sep);
  if (joinedPath.startsWith(PROJECT_DIR)) return joinedPath;
  return path.join(PROJECT_DIR, ...files);
}

/**
 * Configures a logger using the Pino library with multiple transports for logging to
 * files and formatting log output for the console.
 *
 * - `pino-pretty`: Formats logs for console output with colorized and human-readable output.
 * - File Transports:
 *   - `trace` logs to `whatsapp-trace.log` for detailed debugging information.
 *   - `info` logs to `whatsapp-info.log` for general application info.
 *   - General logs are written to `whatsapp-pino.log`.
 */
export const whatsappLogger = pino(
  {
    transport: {
      targets: [
        {
          target: 'pino-pretty',
          options: {
            colorize: true, // Enable colored output in the console
            colorizeObjects: true, // Enable colored output for objects
            ignore: 'pid,hostname', // Exclude `pid` and `hostname` fields from output
            translateTime: 'SYS:standard' // Format timestamps in ISO8601 (RFC3339)
          }
        },
        {
          level: 'trace', // Log level: trace
          target: 'pino/file', // Write logs to a file
          options: {
            ignore: 'pid,hostname', // Exclude `pid` and `hostname` fields from output
            destination: getWhatsappFile('tmp/logs/whatsapp-trace.log') // File path for trace logs
          }
        },
        {
          level: 'info', // Log level: info
          target: 'pino/file', // Write logs to a file
          options: {
            ignore: 'pid,hostname', // Exclude `pid` and `hostname` fields from output
            destination: getWhatsappFile('tmp/logs/whatsapp-info.log') // File path for info logs
          }
        }
      ]
    }
  },
  // Default destination for all logs
  pino.destination(getWhatsappFile('tmp/logs/whatsapp-pino.log')) // Write general logs to this file
);

export const getRandom = (ext: string, lengthArg: number | string = '10') => {
  let length: number = 10;
  if (typeof lengthArg == 'string') {
    length = parseInt(lengthArg);
  } else {
    length = lengthArg;
  }
  let result = '';
  const character = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz1234567890';
  const characterLength = character.length;
  for (let i = 0; i < length; i++) {
    result += character.charAt(Math.floor(Math.random() * characterLength));
  }

  return `${result}.${ext}`;
};

export const fetchBuffer = async (url: any, options?: axios.AxiosRequestConfig<any>) => {
  try {
    const res = await axios.default({
      method: 'GET',
      url,
      headers: {
        'User-Agent':
          'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.70 Safari/537.36',
        DNT: 1,
        'Upgrade-Insecure-Request': 1
      },
      ...options,
      responseType: 'arraybuffer'
    });
    return res.data;
  } catch (err) {
    return err;
  }
};

/**
 * create folder when not exist
 * @param paths directory path
 */
export function initDir(paths: string) {
  if (!fs.existsSync(paths)) fs.mkdirSync(paths, { recursive: true });
}

export function ConsoleFile(baseDir = FOLDER_LOG, errorFileName = 'error', fileName = 'logger') {
  initDir(baseDir);
  return new console.Console(
    fs.createWriteStream(path.join(baseDir, fileName + '.log')),
    fs.createWriteStream(path.join(baseDir, errorFileName + '.log'))
  );
}

export const baileyLogFile = ConsoleFile(FOLDER_LOG);

export function extractPhoneNumber(text: string): string {
  const regex = /(\+?62[-\s]?|0)(\d{3,4}[-\s]?\d{3,4}[-\s]?\d{3,4})/;
  const match = text.match(regex);

  if (match) {
    // Clean up any spaces or dashes and return the matched phone number
    let phoneNumber = match[0].replace(/[-\s]/g, '');
    // Normalize by replacing spaces, dashes, and handling the country code
    phoneNumber = phoneNumber.replace(/[-\s]/g, ''); // Remove spaces and dashes
    if (phoneNumber.startsWith('0')) {
      phoneNumber = '62' + phoneNumber.substring(1); // Replace leading 0 with 62
    } else if (phoneNumber.startsWith('+62')) {
      phoneNumber = '62' + phoneNumber.substring(3); // Replace +62 with 62
    }
    return phoneNumber;
  }

  return ''; // Return an empty string if no match is found
}

export function extractPhoneNumberAndOtp(text: string) {
  const regex = /\/(otp|login|status|check|buy)\s*(\+?62[-\s]?|0)(\d{3,4}[-\s]?\d{3,4}[-\s]?\d{3,4})(?:\s+(\S+))?/;
  const match = text.match(regex);

  if (match) {
    let phoneNumber = match[2] + match[3];

    // Normalize by replacing spaces, dashes, and handling the country code
    phoneNumber = phoneNumber.replace(/[-\s]/g, ''); // Remove spaces and dashes
    if (phoneNumber.startsWith('0')) {
      phoneNumber = '62' + phoneNumber.substring(1); // Replace leading 0 with 62
    } else if (phoneNumber.startsWith('+62')) {
      phoneNumber = '62' + phoneNumber.substring(3); // Replace +62 with 62
    }

    // Extract OTP code if present
    const otpCode = match[4] || null; // Use match[4] for OTP or null if not present

    return { phoneNumber, otpCode };
  }

  return null;
}

export async function loadModule(modulePath: string, debug = false) {
  try {
    const url = modulePath.startsWith('file://') ? modulePath : pathToFileURL(modulePath).href;
    const module = await import(url);
    if (debug) console.log('Module loaded successfully:', module);
    return module;
  } catch (error) {
    if (debug) console.error('Failed to load module:', error);
    // Handle the error or return a fallback
    return null;
  }
}

/**
 * Dumps data into a WhatsApp-specific log file for debugging purposes.
 *
 * @param data - A variadic list of arguments to log. Each argument can be of any type.
 */
export function whatsappDump(...data: unknown[]) {
  const file = getWhatsappFile('tmp/logs/whatsapp-dump.txt');
  try {
    // Map each data item to a string representation
    const mappedData = data.map((item) => {
      if (item === null) return 'NULL';
      if (item === undefined) return 'UNDEFINED';
      if (Array.isArray(item) || typeof item === 'object') {
        try {
          return JSON.stringify(item, null, 2);
        } catch (e) {
          return `[Error Stringifying Object]: ${(e as Error).message}`;
        }
      }
      return item.toString();
    });

    const e = new Error();
    const stack = e.stack || '';
    let callerLineAndFile = stack
      .split('\n')[2]
      .replace(/at\s+file:\/\/\//, '')
      .trim();
    const regex = /\((.*):(\d+):(\d+)\)$/;
    const match1 = regex.exec(stack.split('\n')[2]);
    const match2 = ''.match(/file:\/\/\/(.+):(\d+):(\d+)/);
    let match: RegExpExecArray | RegExpMatchArray | null = null;
    if (match1) {
      match = match1;
    } else if (match2) {
      match = match2;
    }
    if (match) callerLineAndFile = `${match[1]}:${match[2]}:${match[3]}`;

    // Join the mapped data with double newlines for readability
    const logContent = [callerLineAndFile, ...mappedData].join('\n\n');

    // Write the content to the specified WhatsApp log file
    writefile(file, logContent);
  } catch (error) {
    console.error('Failed to dump WhatsApp data:', error);
  }
  return file;
}
