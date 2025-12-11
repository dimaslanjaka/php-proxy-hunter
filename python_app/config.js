import path from 'path';
import { fileURLToPath } from 'url';
import fs from 'fs';

// Base paths
const __filename = fileURLToPath(import.meta.url);
const SCRIPT_DIR = path.dirname(__filename);
const PROJECT_DIR = path.resolve(SCRIPT_DIR, '..');

// Load package.json
const packageJsonPath = path.join(PROJECT_DIR, 'package.json');
/** @type {import('../package.json')} */
const pkg = JSON.parse(fs.readFileSync(packageJsonPath, 'utf-8'));

// Venv paths
const VENV_DIR = path.join(PROJECT_DIR, 'venv');
const VENV_SCRIPTS = path.join(VENV_DIR, 'Scripts');

// Build and output paths
const BUILD_DIR = path.join(PROJECT_DIR, 'build');
const TEMP_DIR = path.join(PROJECT_DIR, 'tmp', 'python');

// App metadata
const COMPANY_NAME = 'WMI';
const VERSION = pkg.version || '1.0.0.0';
const ICON = 'favicon.ico';
const PYTHON = path.join(VENV_SCRIPTS, 'python.exe');

export { SCRIPT_DIR, PROJECT_DIR, VENV_DIR, VENV_SCRIPTS, BUILD_DIR, TEMP_DIR, COMPANY_NAME, VERSION, ICON, PYTHON };
