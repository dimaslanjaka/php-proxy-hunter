#!/usr/bin/env node

'use strict';

import fs from 'fs';
import path from 'path';

const pkg = JSON.parse(fs.readFileSync(path.join(process.cwd(), 'package.json')));
const isESM = pkg.type === 'module';
// const isESM = typeof import.meta !== 'undefined';
// const isCJS = typeof require !== 'undefined' && typeof exports !== 'undefined';

console.log('GitHub Pages Builder running on', isESM ? 'ESM' : 'CJS');

const runner = (lib) => {
  if (typeof lib.default === 'function') {
    lib.default();
  } else if (typeof lib === 'function') {
    lib();
  } else {
    console.error('Error: The imported module does not have a default export or is not a function.');
  }
};

if (isESM) {
  import('../dist/esm/build-gh-pages.js').then(runner);
} else {
  import('../dist/cjs/build-gh-pages.js').then(runner);
}
