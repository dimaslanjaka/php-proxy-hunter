import path from 'path';
import https from 'https';
import fs from 'fs';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const urls = [
  'https://git.io/GeoLite2-ASN.mmdb',
  'https://git.io/GeoLite2-City.mmdb',
  'https://git.io/GeoLite2-Country.mmdb',
  'https://github.com/P3TERX/GeoLite.mmdb/raw/download/GeoLite2-City.mmdb',
  'https://github.com/P3TERX/GeoLite.mmdb/raw/download/GeoLite2-ASN.mmdb',
  'https://github.com/P3TERX/GeoLite.mmdb/raw/download/GeoLite2-Country.mmdb'
];
const saveFolder = path.join(__dirname, '..', 'src');
const downloadFolder = path.join(__dirname, '..', 'tmp/download');

function ensureDir(dir) {
  if (!fs.existsSync(dir)) {
    fs.mkdirSync(dir, { recursive: true });
  }
}

function getRemoteFileSize(url, cb) {
  try {
    const req = https.request(url, { method: 'HEAD' }, (res) => {
      // follow redirects
      if ([301, 302, 303, 307, 308].includes(res.statusCode)) {
        if (res.headers.location) {
          // consume response and follow redirect
          res.resume();
          getRemoteFileSize(res.headers.location, cb);
          return;
        }
      }
      if (res.statusCode !== 200) {
        // consume response body so the socket can close
        res.resume();
        cb(new Error(`Failed to get file size: ${res.statusCode}`), null);
        return;
      }
      const size = res.headers['content-length'] ? parseInt(res.headers['content-length'], 10) : null;
      cb(null, size);
    });
    req.on('error', (err) => cb(err, null));
    req.end();
  } catch (err) {
    cb(err, null);
  }
}

function downloadFile(url, dest, cb) {
  ensureDir(path.dirname(dest));
  const get = (u) => {
    const req = https.get(u, (res) => {
      if ([301, 302, 303, 307, 308].includes(res.statusCode) && res.headers.location) {
        // consume this response and follow redirect
        res.resume();
        get(res.headers.location);
        return;
      }

      if (res.statusCode !== 200) {
        // consume any body so socket can close
        res.resume();
        cb(new Error(`Failed to download file: ${res.statusCode}`));
        return;
      }

      // success: create file stream and pipe
      const file = fs.createWriteStream(dest);
      let finished = false;

      const done = (err) => {
        if (finished) return;
        finished = true;
        try {
          file.close();
        } catch (_) {
          // ignore
        }
        if (err) {
          try {
            if (fs.existsSync(dest)) fs.unlinkSync(dest);
          } catch (_) {
            // ignore
          }
          cb(err);
          return;
        }
        cb(null);
      };

      res.on('error', done);
      file.on('error', done);

      res.pipe(file);
      file.on('finish', () => done(null));
    });

    req.on('error', (err) => {
      try {
        if (fs.existsSync(dest)) fs.unlinkSync(dest);
      } catch (_) {
        // ignore
      }
      cb(err);
    });
  };
  get(url);
}

function processUrl(url, done) {
  const filename = path.basename(url.split('?')[0]);
  const savePath = path.join(saveFolder, filename);
  const tempPath = path.join(downloadFolder, filename);

  ensureDir(saveFolder);
  ensureDir(downloadFolder);

  if (fs.existsSync(savePath)) {
    const localSize = fs.statSync(savePath).size;
    console.log(`Local file ${filename} exists, checking remote size...`);
    getRemoteFileSize(url, (err, remoteSize) => {
      if (err) {
        console.error(`Error getting remote size for ${url}:`, err.message);
        if (typeof done === 'function') done();
        return;
      }
      if (remoteSize === null) {
        console.log(`Remote ${filename} size unknown, skipping download.`);
        if (typeof done === 'function') done();
        return;
      }
      if (remoteSize <= localSize) {
        console.log(
          `Remote ${filename} size (${remoteSize}) is not larger than local (${localSize}), skipping download.`
        );
        if (typeof done === 'function') done();
        return;
      }
      console.log(`Remote ${filename} size (${remoteSize}) is larger than local (${localSize}), downloading...`);
      downloadFile(url, tempPath, (err2) => {
        if (err2) {
          console.error(`Error downloading ${url}:`, err2.message);
          if (typeof done === 'function') done();
          return;
        }
        try {
          fs.copyFileSync(tempPath, savePath);
          console.log(`Updated ${savePath}`);
        } catch (e) {
          console.error(`Error saving ${savePath}:`, e.message);
        }
        if (typeof done === 'function') done();
      });
    });
  } else {
    console.log(`Local file ${filename} not found, downloading...`);
    downloadFile(url, tempPath, (err) => {
      if (err) {
        console.error(`Error downloading ${url}:`, err.message);
        if (typeof done === 'function') done();
        return;
      }
      try {
        fs.copyFileSync(tempPath, savePath);
        console.log(`Saved ${savePath}`);
      } catch (e) {
        console.error(`Error saving ${savePath}:`, e.message);
      }
      if (typeof done === 'function') done();
    });
  }
}

function main() {
  ensureDir(saveFolder);
  ensureDir(downloadFolder);
  let pending = urls.length;
  if (pending === 0) return;
  urls.forEach((u) => {
    processUrl(u, () => {
      pending -= 1;
      if (pending <= 0) {
        console.log('All geoip checks complete. Exiting.');
        // give a tick for any remaining callbacks to settle
        setImmediate(() => process.exit(0));
      }
    });
  });
}

main();
