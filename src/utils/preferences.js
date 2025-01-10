import fs from 'fs-extra';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

/**
 * Gets the caller file name and line number.
 * @returns {string} The caller file and line number.
 */
function getCallerFileAndLine() {
  const originalFunc = Error.prepareStackTrace;

  try {
    const err = new Error();
    let callerFile;

    Error.prepareStackTrace = (err, stack) => stack;
    const currentFile = err.stack.shift().getFileName();

    while (err.stack.length) {
      const frame = err.stack.shift();
      callerFile = frame.getFileName();

      if (callerFile !== currentFile) {
        const lineNumber = frame.getLineNumber();
        const columnNumber = frame.getColumnNumber();
        return `${callerFile}:${lineNumber}:${columnNumber}`;
      }
    }
  } catch (e) {
    console.error('Error while extracting caller file and line:', e);
  } finally {
    Error.prepareStackTrace = originalFunc;
  }

  return undefined;
}

export default class Preferences {
  /**
   * @param {string} filePath Path to the preferences JSON file.
   */
  constructor(filePath = 'tmp/preferences.json') {
    this.filePath = path.resolve(process.cwd(), filePath);
    this.ensureDirExists(path.dirname(this.filePath));
    this.preferences = this.load();
  }

  /**
   * Ensures that the directory for the preferences file exists.
   * @param {string} dirPath The directory path to check/create.
   */
  ensureDirExists(dirPath) {
    try {
      fs.ensureDirSync(dirPath);
    } catch (err) {
      console.error(`Error creating directory: ${err}`);
    }
  }

  /**
   * @type {Record<string, NodeJS.Timeout|null>}
   */
  saveTimeout = {};

  /**
   * Saves the preferences to the JSON file.
   */
  save() {
    const caller = getCallerFileAndLine();
    if (this.saveTimeout[caller]) {
      clearTimeout(this.saveTimeout[caller]);
      this.saveTimeout[caller] = null;
    }
    this.saveTimeout[caller] = setTimeout(() => {
      try {
        fs.writeJsonSync(this.filePath, this.preferences, { spaces: 4 });
      } catch (err) {
        console.error(`Error saving preferences: ${err}`);
      }
    }, 5000);
  }

  /**
   * Loads the preferences from the JSON file.
   * @returns {Object} The preferences object.
   */
  load() {
    try {
      return fs.readJsonSync(this.filePath);
    } catch (err) {
      if (err.code === 'ENOENT') {
        // Return default preferences if the file doesn't exist
        return {};
      } else {
        console.error(`Error loading preferences: ${err}`);
      }
    }
  }

  /**
   * Gets a preference value by key, and checks for expiration.
   * @template T
   * @param {string} key The preference key.
   * @param {T} [defaultValue] The default value to return if the key is not found.
   * @returns {T} The preference value or default.
   */
  get(key, defaultValue = null) {
    const preference = this.preferences[key];
    if (preference) {
      const { value, expiresAt } = preference;
      if (expiresAt && new Date() > new Date(expiresAt)) {
        // Preference expired, remove it and return default
        delete this.preferences[key];
        this.save();
        return defaultValue;
      }
      return value;
    }
    return defaultValue;
  }

  /**
   * Sets a preference key to a value and saves it.
   * @param {string} key The preference key.
   * @param {*} value The value to set.
   */
  set(key, value) {
    this.preferences[key] = { value };
    this.save();
  }

  /**
   * Sets a preference with an expiration time (in hours).
   * @param {string} key The preference key.
   * @param {*} value The value to set.
   * @param {number} hours The number of hours until the preference expires.
   */
  setWithTimeout(key, value, hours) {
    const expiresAt = new Date();
    expiresAt.setHours(expiresAt.getHours() + hours);
    this.preferences[key] = { value, expiresAt };
    this.save();
  }
}
