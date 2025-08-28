import fs from 'fs';
import https from 'https';
import path from 'path';

// Destination folder
const DEST = path.join(process.cwd(), 'adminer');
if (!fs.existsSync(DEST)) fs.mkdirSync(DEST, { recursive: true });

// URLs and output paths
const files = [
  {
    url: 'https://github.com/vrana/adminer/releases/download/v5.3.0/adminer-5.3.0-en.php',
    output: path.join(DEST, 'index.php')
  },
  {
    url: 'https://www.adminer.org/static/download/5.3.0/editor-5.3.0-en.php',
    output: path.join(DEST, 'editor.php')
  }
];

/**
 * Downloads a file from the specified URL and saves it to the given destination path.
 * Follows HTTP redirects if necessary.
 *
 * @param {string} url - The URL of the file to download.
 * @param {string} dest - The local file path where the downloaded file will be saved.
 * @returns {Promise<void>} Resolves when the file has been downloaded and saved.
 */
function download(url, dest) {
  return new Promise((resolve, reject) => {
    const file = fs.createWriteStream(dest);
    https
      .get(url, (res) => {
        if (res.statusCode >= 300 && res.statusCode < 400 && res.headers.location) {
          // Follow redirect
          console.log(`Redirecting to ${res.headers.location}`);
          return download(res.headers.location, dest).then(resolve).catch(reject);
        }
        res.pipe(file);
        file.on('finish', () =>
          file.close((e) => {
            if (!e) {
              resolve();
            } else {
              reject(e);
            }
          })
        );
      })
      .on('error', (err) => {
        fs.unlink(dest, () => reject(err));
      });
  });
}

function modify() {
  // replace `namespace Adminer;` with custom script
  for (const file of files) {
    const content = fs.readFileSync(file.output, 'utf-8');
    const modified = content.replace(
      /namespace\s+Adminer;/,
      `namespace Adminer;

require_once __DIR__ . '/../func.php';
// only allow administrator
// edit administrator email on /data/login.php
if (!isset($_SESSION['admin'])) {
  exit('disallow access');
}

      `
    );
    fs.writeFileSync(file.output, modified);
  }
}

// Download all files
(async () => {
  try {
    for (const f of files) {
      console.log(`Downloading ${f.url} -> ${f.output}`);
      await download(f.url, f.output);
    }
    await modify();
    console.log('All files downloaded successfully!');
  } catch (err) {
    console.error('Download failed:', err);
  }
})();
