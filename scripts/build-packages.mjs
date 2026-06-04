#!/usr/bin/env node
/**
 * Build packages checksum script
 *
 * Walks all packages/PACKAGE/src/ for source files, computes per-package
 * SHA-256 checksums.  Used to detect source changes so builds can be
 * skipped when nothing changed.
 *
 * Included extensions: .ts .cjs .mjs .js
 * Excluded patterns: *.runner.*  *.direct.*  *.builder.*
 *
 * Output: newline-delimited JSON (one line per package with content).
 */
import crypto from 'node:crypto';
import fs from 'fs-extra';
import path from 'upath';
import { fileURLToPath } from 'node:url';
import * as glob from 'glob';
import * as cp from 'cross-spawn';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const ROOT = path.resolve(__dirname, '..');
const PACKAGES_DIR = path.join(ROOT, 'packages');
const previousChecksumsPath = path.join(ROOT, 'tmp/build/packages-checksums.json');
let previousChecksums;
try {
  const data = fs.readJSONSync(previousChecksumsPath);
  previousChecksums = new Set(data.map((e) => e.checksum));
} catch {
  previousChecksums = new Set();
}

function collectChecksum() {
  /** @type {{ package: string; checksum: string; count: number }[]} */
  const computedChecksums = [];
  /** @type {string[]} */
  let packageDirs;

  try {
    packageDirs = fs
      .readdirSync(PACKAGES_DIR, { withFileTypes: true })
      .filter((d) => d.isDirectory())
      .map((d) => d.name);
  } catch (err) {
    console.error(`Failed to read packages directory "${PACKAGES_DIR}": ${err.message}`);
    process.exit(1);
  }

  for (const pkg of packageDirs) {
    let files = glob.globSync('src/**/*.{ts,cjs,mjs,js}', {
      cwd: path.join(PACKAGES_DIR, pkg),
      nodir: true,
      ignore: ['**/*.runner.*', '**/*.direct.*', '**/*.builder.*']
    });
    if (files.length === 0) continue;

    // Sort for deterministic ordering across runs.
    files.sort();

    const hash = crypto.createHash('sha256');
    for (const file of files) {
      // Mix in the relative path so file moves are detected.
      const relPath = path.join(pkg, file);
      hash.update(`${relPath}\0`);
      hash.update(fs.readFileSync(path.join(PACKAGES_DIR, pkg, file)));
    }

    const checksum = hash.digest('hex');

    const pkgJsonPath = path.join(PACKAGES_DIR, pkg, 'package.json');
    if (!fs.existsSync(pkgJsonPath)) {
      console.warn(`Warning: package "${pkg}" does not have a package.json, skipping.`);
      continue;
    }
    /** @type {import('../package.json')} */
    const pkgJson = fs.readJSONSync(pkgJsonPath);
    computedChecksums.push({ package: pkgJson.name, path: pkg, checksum, count: files.length });
  }

  return computedChecksums;
}

function buildPackage(pkgName, folder) {
  const cwd = path.join(PACKAGES_DIR, folder);
  console.log(`Building package: ${pkgName} (${cwd})`);
  const result = cp.spawnSync('yarn', ['run', 'build'], {
    cwd,
    stdio: 'inherit',
    shell: true
  });
  if (result.status !== 0) {
    throw new Error(`Build failed for "${pkgName}" (exit code ${result.status})`);
  }
}

function checkForChanges() {
  const computedChecksums = collectChecksum();

  if (previousChecksums.size === 0) {
    console.log('No previous checksums found, building all packages.');
    for (const { package: pkg, path: folder } of computedChecksums) {
      buildPackage(pkg, folder);
    }
  } else {
    const changedPackages = computedChecksums.filter(({ checksum }) => !previousChecksums.has(checksum));
    console.log(`Found ${changedPackages.length} changed package(s).`);
    for (const { package: pkg, path: folder } of changedPackages) {
      buildPackage(pkg, folder);
    }
  }

  // Save current checksums for next run.
  fs.ensureDirSync(path.dirname(previousChecksumsPath));
  fs.writeJSONSync(previousChecksumsPath, computedChecksums, { spaces: 2 });
}

try {
  checkForChanges();
} catch (err) {
  console.error(err.message);
  process.exit(1);
}
