import fs from 'fs';
import path from 'path';
import { writefile } from 'sbg-utility';
import { projectDir } from './init.js';

const logFilePath = path.resolve(projectDir, 'tmp/github-pages-builder.log');
// Always reset log file at startup
// This ensures that each run starts with a clean log file
// and avoids appending to previous runs' logs.
// This is useful for debugging and tracking issues in the current run.
writefile(logFilePath, '');

export const nodeConsole = globalThis.console;

export default class Logger {
  protected static debug = false;

  static setDebug(value: boolean) {
    Logger.debug = value;
  }

  static log(message: string, ...args: any[]) {
    const timestamp = new Date().toISOString();
    const formattedMessage = `[${timestamp}] ${message}`;

    if (Logger.debug) {
      console.log(formattedMessage, ...args);
    }

    fs.appendFileSync(logFilePath, formattedMessage + '\n', 'utf8');
  }

  static warn(message: string, ...args: any[]) {
    const timestamp = new Date().toISOString();
    const formattedMessage = `[${timestamp}] WARN: ${message}`;

    if (Logger.debug) {
      console.warn(formattedMessage, ...args);
    }

    fs.appendFileSync(logFilePath, formattedMessage + '\n', 'utf8');
  }

  static error(message: string, ...args: any[]) {
    const timestamp = new Date().toISOString();
    const formattedMessage = `[${timestamp}] ERROR: ${message}`;

    if (Logger.debug) {
      console.error(formattedMessage, ...args);
    }

    fs.appendFileSync(logFilePath, formattedMessage + '\n', 'utf8');
  }
}
