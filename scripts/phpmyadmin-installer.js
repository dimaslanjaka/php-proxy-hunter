/**
 * Auto download and extract phpMyAdmin with Node.js
 */

import fs from 'fs';
import path from 'path';
import https from 'https';
import crypto from 'crypto';
import * as unzipper from 'unzipper';

const CWD = process.cwd();
const TMP_DIR = path.join(CWD, 'tmp', 'download');
const DOWNLOAD_FILE = path.join(TMP_DIR, 'phpmyadmin.zip');
const EXTRACT_DIR = path.join(CWD, 'phpmyadmin');

// Hardcoded phpMyAdmin version and URL
const PHPMYADMIN_VERSION = '5.2.2';
const PHPMYADMIN_URL = `https://files.phpmyadmin.net/phpMyAdmin/${PHPMYADMIN_VERSION}/phpMyAdmin-${PHPMYADMIN_VERSION}-all-languages.zip`;

/**
 * Get remote file size (works for most servers, but phpMyAdmin supports HEAD)
 */
async function getRemoteFileSize(url) {
  return new Promise((resolve, reject) => {
    https
      .get(url, { method: 'HEAD' }, (res) => {
        if (res.statusCode !== 200) {
          return reject(new Error(`Failed to fetch HEAD: ${res.statusCode}`));
        }
        resolve(parseInt(res.headers['content-length'] || '0', 10));
      })
      .on('error', reject);
  });
}

/**
 * Downloads a file from the specified URL and saves it to the given output path.
 * Resolves when the download is complete.
 * Rejects if the HTTP status is not 200 or if an error occurs during download.
 *
 * @param {string} url - The URL to download the file from.
 * @param {string} output - The local file path where the downloaded file will be saved.
 * @returns {Promise<void>} Resolves when the file is downloaded successfully.
 * @throws {Error} If the download fails or the HTTP status is not 200.
 */
async function downloadFile(url, output) {
  console.log(`Downloading: ${url}`);
  return new Promise((resolve, reject) => {
    const file = fs.createWriteStream(output);
    https
      .get(url, (res) => {
        if (res.statusCode !== 200) {
          file.close();
          fs.unlinkSync(output);
          return reject(new Error(`Failed to download: ${res.statusCode}`));
        }
        res.pipe(file);
        file.on('finish', () =>
          file.close((err) => {
            if (!err) {
              resolve(undefined);
            } else {
              reject(err);
            }
          })
        );
      })
      .on('error', (err) => {
        file.close();
        fs.unlinkSync(output);
        reject(err);
      });
  });
}

/**
 * Extracts a zip file to the specified target directory using unzipper, with progress output.
 *
 * @param {string} zipFile - The path to the zip file to extract.
 * @param {string} targetDir - The directory where the contents will be extracted.
 * @returns {Promise<void>} Resolves when extraction is complete.
 * @throws {Error} If extraction fails.
 */
async function extractZip(zipFile, targetDir) {
  console.log(`Extracting to ${targetDir} ...`);
  // Get total number of entries for progress
  const directory = await unzipper.Open.file(zipFile);
  const total = directory.files.filter((f) => f.type !== 'Directory').length;
  let count = 0;
  await new Promise((resolve, reject) => {
    fs.createReadStream(zipFile)
      .pipe(unzipper.Parse())
      .on('entry', function (entry) {
        // Remove the first directory from the path
        /** @type {string[]} */
        const parts = entry.path.split(/[/\\]/);
        parts.shift(); // Remove the top-level folder
        const relativePath = parts.join(path.sep);
        if (!relativePath) {
          entry.autodrain();
          return;
        }
        const filePath = path.join(targetDir, relativePath);
        if (entry.type === 'Directory') {
          fs.mkdirSync(filePath, { recursive: true });
          entry.autodrain();
        } else {
          count++;
          const percent = ((count / total) * 100).toFixed(1);
          const progressMsg = `${percent}% (${count}/${total})`;
          process.stdout.write('\r' + progressMsg + ' '.repeat(60)); // pad with spaces
          const dir = path.dirname(filePath);
          fs.mkdirSync(dir, { recursive: true });
          entry.pipe(fs.createWriteStream(filePath));
        }
      })
      .on('close', () => {
        process.stdout.write('\nExtraction complete. Total files: ' + count + '\n');
        resolve();
      })
      .on('error', reject);
  });
}

function createConfigFile() {
  // Generate a 32-byte random string for blowfish_secret (base64, 43 chars, no padding)
  const blowfishSecret = crypto.randomBytes(32).toString('base64').replace(/=+$/, '');
  const configContent = `
<?php

/**
 * This is needed for cookie based authentication to encrypt the cookie.
 * Needs to be a 32-bytes long string of random bytes. See FAQ 2.10.
 */
$cfg['blowfish_secret'] = '${blowfishSecret}'; /* YOU MUST FILL IN THIS FOR COOKIE AUTH! */

/**
 * First server
 */
$i++;
/* Authentication type */
$cfg['Servers'][$i]['auth_type'] = 'cookie';
/* Server parameters */
$cfg['Servers'][$i]['host'] = 'localhost';
$cfg['Servers'][$i]['compress'] = false;
$cfg['Servers'][$i]['AllowNoPassword'] = false;

/*
 * Laragon: set phpmyadmin to not timeout so quickly
 */
$cfg['LoginCookieValidity'] = 604800; // 1 week in seconds
$cfg['LoginCookieStore'] = 604800; // 1 week in seconds

/**
 * Directories for saving/loading files from server
 */
$cfg['UploadDir'] = __DIR__ . '/tmp/upload';
$cfg['SaveDir'] = __DIR__ . '/tmp/save';

`.trim();
  const configPath = path.join(EXTRACT_DIR, 'config.inc.php');
  fs.writeFileSync(configPath, configContent);
  console.log(`Created config.inc.php at ${configPath} with upload and save directories set.`);
}

/**
 * Main
 */
async function main() {
  try {
    console.log(`phpMyAdmin version: ${PHPMYADMIN_VERSION}`);
    console.log(`Download URL: ${PHPMYADMIN_URL}`);

    // Ensure tmp and target directories exist
    fs.mkdirSync(TMP_DIR, { recursive: true });
    fs.mkdirSync(EXTRACT_DIR, { recursive: true });

    // Download only if remote and local size differ
    let localSize = 0;
    if (fs.existsSync(DOWNLOAD_FILE)) {
      localSize = fs.statSync(DOWNLOAD_FILE).size;
    }
    let remoteSize = 0;
    try {
      remoteSize = await getRemoteFileSize(PHPMYADMIN_URL);
    } catch {
      console.warn('Warning: Could not get remote file size, will download anyway.');
      remoteSize = -1;
    }
    if (remoteSize !== localSize) {
      console.log('File size differs. Downloading phpMyAdmin...');
      await downloadFile(PHPMYADMIN_URL, DOWNLOAD_FILE);
    } else {
      console.log('Local file is up to date. Skipping download.');
    }

    // Always extract and create config
    await extractZip(DOWNLOAD_FILE, EXTRACT_DIR);
    createConfigFile();
    console.log('Done ✅ (zip kept in tmp/download)');
  } catch (err) {
    console.error('Error:', err);
  }
}

main();
