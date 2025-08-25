/**
 * Auto download and extract phpMyAdmin with Node.js
 */

import fs from 'fs';
import path from 'path';
import https from 'https';
import { execSync } from 'child_process';
import crypto from 'crypto';

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
 * Download file
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
        file.on('finish', () => file.close(resolve));
      })
      .on('error', (err) => {
        file.close();
        fs.unlinkSync(output);
        reject(err);
      });
  });
}

/**
 * Extract zip file using system tools (PowerShell on Windows, unzip on others)
 */
async function extractZip(zipFile, targetDir) {
  console.log(`Extracting to ${targetDir} ...`);
  if (process.platform === 'win32') {
    execSync(`powershell -Command "Expand-Archive -Path '${zipFile}' -DestinationPath '${targetDir}' -Force"`, {
      stdio: 'inherit'
    });
  } else {
    execSync(`unzip -o '${zipFile}' -d '${targetDir}'`, { stdio: 'inherit' });
  }
  console.log('Extraction complete.');
}

function createConfigFile() {
  // Generate a 32-byte random string for blowfish_secret (base64, 43 chars, no padding)
  const blowfishSecret = crypto.randomBytes(32).toString('base64').replace(/=+$/, '');
  const configContent = `
<?php

declare(strict_types=1);

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
$cfg['LoginCookieValidity'] = 36000;

/**
 * Directories for saving/loading files from server
 */
$cfg['UploadDir'] = __DIR__ . '/tmp/upload';
$cfg['SaveDir'] = __DIR__ . '/tmp/save';

`.trim();
  const configPath = path.join(EXTRACT_DIR, `phpMyAdmin-${PHPMYADMIN_VERSION}-all-languages`, 'config.inc.php');
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
      await extractZip(DOWNLOAD_FILE, EXTRACT_DIR);
      createConfigFile();
    } else {
      console.log('Local file is up to date. Skipping download.');
    }

    console.log('Done ✅ (zip kept in tmp/download)');
  } catch (err) {
    console.error('Error:', err);
  }
}

main();
