const cp = require('cross-spawn');
const path = require('path');
const glob = require('glob');
// const fs = require('fs');
// const env = require('../.env.cjs');

const cwd = path.join(__dirname, '../');
const rollupConfigs = glob.globSync('rollup.*.{js,cjs}', { cwd, absolute: true });

for (const f of rollupConfigs) {
  const filename = path.basename(f);
  console.log('building', filename);
  cp.spawnSync('rollup', ['-c', f], { stdio: 'inherit', cwd });
}
