import * as axios from 'axios';
import fs from 'fs-extra';
import { pathToFileURL } from 'node:url';
import path from 'upath';
import { PROJECT_DIR } from '../.env.mjs';

export const FOLDER_CONFIG = './baileys_auth_info';
export const FOLDER_LOG = './baileys_auth_info/log';

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

export function getWhatsappFile(...files: string[]) {
  return path.join(PROJECT_DIR, ...files);
}
