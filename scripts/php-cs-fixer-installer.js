import fs from 'fs';
import https from 'https';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const url = 'https://cs.symfony.com/download/php-cs-fixer-v3.phar';
const outputPath = path.join('vendor', 'bin', 'php-cs-fixer.phar');

/**
 * Get the size (in bytes) of a remote resource by URL.
 * Uses a HEAD request to read the Content-Length header and falls back to a GET
 * when HEAD does not provide a content-length or returns an error.
 *
 * @param {string} remoteUrl - Fully-qualified URL to the remote file.
 * @returns {Promise<number|null>} Resolves to the size in bytes, or `null` if unknown.
 * @rejects {Error} When network errors occur or the request times out.
 */
async function getRemoteSize(remoteUrl) {
  return new Promise((resolve, reject) => {
    try {
      const req = https.request(remoteUrl, { method: 'HEAD', agent: false }, (res) => {
        if (res.statusCode && res.statusCode >= 400) {
          // fallback to GET to try to read headers
          // destroy the HEAD response to free the socket
          try {
            res.destroy();
          } catch (_err) {
            // ignore
          }

          const g = https.get(remoteUrl, { agent: false }, (r2) => {
            const len2 = r2.headers['content-length'];
            try {
              r2.destroy();
            } catch (_err) {
              // ignore
            }
            resolve(len2 ? parseInt(len2, 10) : null);
          });
          g.on('error', (err) => reject(err));
          // guard against hanging GET
          try {
            g.setTimeout(10000, () => {
              try {
                g.destroy();
              } catch (_err) {
                // ignore
              }
              reject(new Error('GET timeout (fallback)'));
            });
          } catch (_e) {
            // some node versions may not have setTimeout on ClientRequest
          }
          return;
        }

        const len = res.headers['content-length'];
        if (len) {
          // consume/destroy the response so the socket is closed and the process can exit
          try {
            res.destroy();
          } catch (_err) {
            // ignore
          }
          return resolve(parseInt(len, 10));
        }

        // No content-length on HEAD, fallback to GET
        const g = https.get(remoteUrl, { agent: false }, (r2) => {
          const len2 = r2.headers['content-length'];
          try {
            r2.destroy();
          } catch (_err) {
            // ignore
          }
          resolve(len2 ? parseInt(len2, 10) : null);
        });
        g.on('error', (err) => reject(err));
        try {
          g.setTimeout(10000, () => {
            try {
              g.destroy();
            } catch (_err) {
              // ignore
            }
            reject(new Error('GET timeout (fallback)'));
          });
        } catch (_e) {
          // ignore
        }
      });

      req.on('error', (err) => reject(err));
      // prevent hanging requests
      req.setTimeout(10000, () => {
        try {
          req.destroy();
        } catch (_err) {
          // ignore
        }
        reject(new Error('HEAD request timeout'));
      });
      req.end();
    } catch (err) {
      reject(err);
    }
  });
}

/**
 * Download a file to disk.
 * The file is streamed to a temporary path (`destPath + '.tmp'`) and renamed to
 * `destPath` after the download completes successfully.
 *
 * @param {string} remoteUrl - Remote URL to download from.
 * @param {string} destPath - Local filesystem path to write the downloaded file to.
 * @returns {Promise<void>} Resolves when the file has been written and moved into place.
 * @rejects {Error} On HTTP errors, write errors, or when the download fails.
 */
async function downloadFile(remoteUrl, destPath) {
  return new Promise((resolve, reject) => {
    const tmp = destPath + '.tmp';
    const file = fs.createWriteStream(tmp);

    const req = https.get(remoteUrl, (res) => {
      if (res.statusCode && res.statusCode >= 400) {
        file.close(() => fs.unlink(tmp, () => {}));
        return reject(new Error('Failed to download, HTTP status ' + res.statusCode));
      }

      res.pipe(file);
      file.on('finish', () => {
        file.close((err) => {
          if (err) return reject(err);
          try {
            fs.renameSync(tmp, destPath);
          } catch (e) {
            return reject(e);
          }
          // try to set executable bit where supported
          try {
            fs.chmodSync(destPath, 0o755);
          } catch {
            // ignore chmod errors on unsupported platforms
          }
          resolve();
        });
      });
    });

    req.on('error', (err) => {
      try {
        file.close();
        fs.unlinkSync(tmp);
      } catch {
        // ignore
      }
      reject(err);
    });

    file.on('error', (err) => {
      try {
        file.close();
        fs.unlinkSync(tmp);
      } catch {
        // ignore
      }
      reject(err);
    });
  });
}

/**
 * Ensure the directory for a given file path exists.
 * Creates parent directories recursively if required.
 *
 * @param {string} filePath - File path for which to ensure the parent directory exists.
 * @returns {void}
 */
function ensureDirFor(filePath) {
  const dir = path.dirname(filePath);
  if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
}

(async function main() {
  const dest = path.resolve(__dirname, '..', outputPath);
  ensureDirFor(dest);

  let shouldDownload = false;

  if (!fs.existsSync(dest)) {
    console.log('php-cs-fixer not found locally, will download to', dest);
    shouldDownload = true;
  } else {
    try {
      const localSize = fs.statSync(dest).size;
      let remoteSize = null;
      try {
        remoteSize = await getRemoteSize(url);
      } catch (e) {
        console.warn('Could not determine remote size:', e.message || e);
      }

      if (remoteSize === null) {
        // Unknown remote size, choose to download to be safe
        console.log('Remote size unknown; re-downloading to ensure latest version.');
        shouldDownload = true;
      } else if (localSize !== remoteSize) {
        console.log(`Local size (${localSize}) differs from remote (${remoteSize}); updating.`);
        shouldDownload = true;
      } else {
        console.log('php-cs-fixer is up-to-date. No download needed.');
      }
    } catch (e) {
      console.warn('Error checking local file size, will attempt download:', e.message || e);
      shouldDownload = true;
    }
  }

  if (shouldDownload) {
    try {
      console.log('Downloading php-cs-fixer from', url);
      await downloadFile(url, dest);
      console.log('Downloaded php-cs-fixer to', dest);
    } catch (e) {
      console.error('Download failed:', e.message || e);
      process.exitCode = 2;
    }
  }
})();
