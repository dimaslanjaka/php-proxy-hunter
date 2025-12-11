import path from 'path';
import { spawn } from 'child_process';
import { SCRIPT_DIR, PROJECT_DIR, COMPANY_NAME, VERSION, ICON, PYTHON, VENV_SCRIPTS } from './config.js';
import { PYTHONPATH } from './pythonFinder.js';

export async function buildApp() {
  // Build arguments for nuitka
  const nuitkaArgs = [
    path.join(SCRIPT_DIR, 'proxyListGui.py'),
    '--output-file=proxyListGui.exe',
    '--report=build/nuitka-proxyListGui-report.xml',
    '--output-dir=build',
    `--windows-icon-from-ico=${ICON}`,
    `--windows-company-name=${COMPANY_NAME}`,
    '--windows-product-name=proxyListGui',
    `--windows-file-version=${VERSION}`,
    '--onefile',
    '--noinclude-unittest-mode=allow',
    '--include-data-dir=assets/database=assets/database',
    // '--include-data-dir=assets/chrome=assets/chrome',
    // '--include-data-dir=assets/chrome-extensions=assets/chrome-extensions',
    '--include-data-dir=js=js',
    '--include-data-file=favicon.ico=favicon.ico',
    // '--include-data-files=src/*.mmdb=src/',
    '--include-data-files=data/*.pem=data/',
    '--msvc=latest',
    '--enable-plugin=pyside6',
    '--include-qt-plugins=all',
    '--experimental=debug-report-traceback',
    // '--include-package=selenium',
    // '--include-package-data=selenium',
    // '--include-package=selenium_stealth',
    // '--include-package-data=selenium_stealth',
    // '--include-package=webdriver_manager',
    // '--include-package-data=webdriver_manager',
    '--windows-console-mode=force',
    // '--enable-plugin=tk-inter',
    '--nofollow-import-to=tkinter',
    '--nofollow-import-to=unittest',
    '--nofollow-import-to=pytest',
    '--nofollow-import-to=doctest',
    '--nofollow-import-to=pydoc',
    '--nofollow-import-to=setuptools',
    '--nofollow-import-to=test',
    '--nofollow-import-to=django',
    '--nofollow-import-to=distutils',
    '--jobs=1'
  ];

  console.log(`PROJECT_DIR=${PROJECT_DIR}`);
  console.log(`PYTHON=${PYTHON}`);
  console.log(`Script location: ${SCRIPT_DIR}`);

  // Prefer the PYTHONPATH exported by `pythonFinder.js`; fall back to
  // `PROJECT_DIR` plus any existing `process.env.PYTHONPATH` if it's empty.

  // Execute nuitka compilation
  // console.log(`\nRunning: "${PYTHON}" -m nuitka ...\n`);

  const child = spawn(PYTHON, ['-m', 'nuitka', ...nuitkaArgs], {
    stdio: 'inherit',
    cwd: PROJECT_DIR,
    env: {
      ...process.env,
      PYTHONPATH,
      NUITKA_CACHE_DIR: `${PROJECT_DIR}/tmp/nuitka-cache`,
      PATH: `${VENV_SCRIPTS}${path.delimiter}${process.env.PATH}`
    }
  });

  child.on('close', (code) => {
    if (code === 0) {
      console.log('\nBuild completed successfully!');
      process.exit(0);
    } else {
      console.error(`Build failed with exit code ${code}`);
      process.exit(code);
    }
  });

  child.on('error', (err) => {
    console.error('Failed to start build process:', err);
    process.exit(1);
  });
}

if (process.argv.some((arg) => arg.includes('build.js'))) {
  buildApp().catch(console.error);
}
