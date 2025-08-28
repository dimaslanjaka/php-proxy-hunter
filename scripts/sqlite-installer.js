/**
 * Auto-download and extract the latest SQLite precompiled binary
 * for the current OS/arch.
 */

import https from 'https';
import os from 'os';
import path from 'upath';
import fs from 'fs-extra';
import { execSync } from 'child_process';

// === Fetch download page and extract CSV ===
async function fetchDownloadCSV() {
  return new Promise((resolve, reject) => {
    https
      .get('https://www.sqlite.org/download.html', (res) => {
        let data = '';
        res.on('data', (chunk) => (data += chunk));
        res.on('end', () => {
          const match = data.match(/<!--\s*Download product data([\s\S]*?)-->/);
          if (!match) return reject(new Error('Download CSV not found'));
          const csv = match[1].trim().split('\n');
          resolve(csv);
        });
      })
      .on('error', reject);
  });
}

// === Pick best binary for platform/arch ===
function pickDownload(csvLines) {
  const platform = os.platform();
  const arch = os.arch();

  let target;
  if (platform === 'win32') {
    target = arch === 'x64' ? 'win-x64' : 'win-x86';
  } else if (platform === 'darwin') {
    target = arch === 'arm64' ? 'osx-arm64' : 'osx-x86';
  } else if (platform === 'linux') {
    target = arch === 'arm64' ? 'linux-aarch64' : 'linux-x86_64';
  } else {
    throw new Error(`Unsupported platform: ${platform} ${arch}`);
  }

  const tool = csvLines.map((line) => line.split(',')).find((f) => f[2] && f[2].includes(`sqlite-tools-${target}`));

  if (!tool) throw new Error(`No sqlite-tools found for ${target}`);

  return {
    relative: tool[2],
    filename: path.basename(tool[2])
  };
}

// === Download helper ===
async function downloadFile(url, dest) {
  return new Promise((resolve, reject) => {
    const file = fs.createWriteStream(dest);
    https
      .get(url, (res) => {
        if (res.statusCode !== 200) {
          reject(new Error(`Download failed: ${res.statusCode}`));
          return;
        }
        res.pipe(file);
        file.on('finish', () => file.close(resolve));
      })
      .on('error', reject);
  });
}

// === Check if download is needed ===
async function shouldDownload(url, local) {
  if (!fs.existsSync(local)) return true;
  const localSize = fs.statSync(local).size;
  return new Promise((resolve, reject) => {
    https
      .get(url, { method: 'HEAD' }, (res) => {
        const remoteSize = parseInt(res.headers['content-length'], 10);
        if (!remoteSize || isNaN(remoteSize)) {
          return resolve(true);
        }
        resolve(localSize !== remoteSize);
      })
      .on('error', reject);
  });
}

// === Main ===
(async () => {
  try {
    console.log('Fetching SQLite download list...');
    const csv = await fetchDownloadCSV();
    const { relative, filename } = pickDownload(csv);

    const base = 'https://www.sqlite.org';
    const url = `${base}/${relative}`;
    console.log('Resolved URL:', url);

    const ext = path.extname(filename);
    // Save download to process.cwd()/tmp/download
    const tmpDir = path.resolve(process.cwd(), 'tmp', 'download');
    if (!fs.existsSync(tmpDir)) {
      fs.mkdirSync(tmpDir, { recursive: true });
    }
    const local = path.join(tmpDir, filename);

    // Set extraction directory to /bin in process.cwd()
    const binDir = path.resolve(process.cwd(), 'bin');
    if (!fs.existsSync(binDir)) {
      fs.mkdirSync(binDir, { recursive: true });
    }

    if (await shouldDownload(url, local)) {
      console.log('Downloading:', filename);
      await downloadFile(url, local);
      console.log('Download complete:', local);
    } else {
      console.log('Local file is up to date, skipping download.');
    }

    console.log('Extracting...');
    if (ext === '.zip') {
      // Use unzipper for progress
      const unzipper = await import('unzipper');
      const directory = await unzipper.Open.file(local);
      const total = directory.files.filter((f) => f.type !== 'Directory').length;
      let count = 0;
      await new Promise((resolve, reject) => {
        fs.createReadStream(local)
          .pipe(unzipper.Parse())
          .on('entry', function (entry) {
            let relativePath;
            const parts = entry.path.split(/[/\\]/);
            if (parts.length > 1) {
              relativePath = parts.slice(1).join(path.sep);
            } else {
              relativePath = entry.path;
            }
            if (!relativePath) {
              entry.autodrain();
              return;
            }
            const filePath = path.join(binDir, relativePath);
            if (entry.type === 'Directory') {
              fs.mkdirSync(filePath, { recursive: true });
              entry.autodrain();
            } else {
              count++;
              const percent = ((count / total) * 100).toFixed(1);
              process.stdout.write(`\r${percent}% (${count}/${total})` + ' '.repeat(40));
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
    } else if (ext === '.gz') {
      execSync(`mkdir -p '${binDir}' && tar -xzf '${local}' -C '${binDir}' --strip-components=1`, { stdio: 'inherit' });
    }

    // Auto-create bin/sqlite3.cmd for Windows CLI usage, only if sqlite3.exe exists
    const exePath = path.join(binDir, 'sqlite3.exe');
    if (fs.existsSync(exePath)) {
      const cmdScript = `@echo off\r\nREM Forward all arguments to sqlite3.exe in the same directory\r\nset SCRIPT_DIR=%~dp0\r\n"%SCRIPT_DIR%sqlite3.exe" %*\r\n`;
      const cmdPath = path.join(binDir, 'sqlite3.cmd');
      await fs.writeFile(cmdPath, cmdScript, 'utf8');
    }

    console.log('✅ SQLite installed in ./bin/');
    console.log('Run ./bin/sqlite3[.exe] --version to verify.');
  } catch (err) {
    console.error('❌ Error:', err.message);
  }
})();
