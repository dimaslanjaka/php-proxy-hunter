const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

function isExecutable(p) {
  if (!p) return false;
  if (!fs.existsSync(p)) return false;

  if (process.platform === 'win32') return true; // exe presence is enough

  try {
    fs.accessSync(p, fs.constants.X_OK);
    return true;
  } catch {
    return false;
  }
}

function windowsExpand(drives, relativePaths) {
  return drives.flatMap((d) => relativePaths.map((r) => path.join(d + path.sep, r)));
}

function fallbackWhich(commands) {
  for (const cmd of commands) {
    try {
      const out = execSync(cmd, { stdio: ['ignore', 'pipe', 'ignore'] })
        .toString()
        .split(/\r?\n/)
        .filter(Boolean);

      if (out.length) return out[0].trim();
    } catch {
      // ignore
    }
  }
  return null;
}

function findExecutable({ winPaths = [], nixPaths = [], extraLocal = [] }) {
  const candidates = [];

  // include extra local venv paths first
  for (const file of extraLocal) {
    if (isExecutable(file)) return file;
    candidates.push(file);
  }

  if (process.platform === 'win32') {
    const drives = ['C:', 'D:', 'E:', 'F:', 'G:'];
    candidates.push(...windowsExpand(drives, winPaths));
  } else {
    candidates.push(...nixPaths);
  }

  // check file existence
  for (const c of candidates) {
    if (isExecutable(c)) return c;
  }

  // fallback
  const which = fallbackWhich(process.platform === 'win32' ? ['where php'] : ['which php', 'which php8', 'which php7']);

  return which || 'php';
}

function findPhpExecutable() {
  return findExecutable({
    winPaths: [
      'php\\php.exe',
      'Program Files\\PHP\\php.exe',
      'Program Files (x86)\\PHP\\php.exe',
      'xampp\\php\\php.exe',
      'wamp\\bin\\php\\php.exe'
    ],
    nixPaths: ['/usr/bin/php', '/usr/local/bin/php']
  });
}

function findPythonExecutable() {
  const root = path.resolve(__dirname, '../../../');

  return findExecutable({
    winPaths: [
      'Python39\\python.exe',
      'Python38\\python.exe',
      'Python37\\python.exe',
      'Program Files\\Python39\\python.exe',
      'Program Files (x86)\\Python39\\python.exe'
    ],
    nixPaths: ['/usr/bin/python3', '/usr/local/bin/python3'],
    extraLocal: [
      path.resolve(root, 'venv', 'Scripts', 'python.exe'),
      path.resolve(root, '.venv', 'Scripts', 'python.exe'),
      // Unix-style virtualenv locations
      path.resolve(root, 'venv', 'bin', 'python'),
      path.resolve(root, '.venv', 'bin', 'python')
    ]
  });
}

module.exports = { findPhpExecutable, findPythonExecutable };

// If executed directly, print
if (require.main === module) {
  const result = {
    php: findPhpExecutable(),
    python: findPythonExecutable()
  };
  const outputFile = path.resolve(__dirname, 'executables.json');
  fs.writeFileSync(outputFile, JSON.stringify(result, null, 2), 'utf-8');
  console.log(`Executables found written to ${outputFile}`);
  console.log(result);
}
