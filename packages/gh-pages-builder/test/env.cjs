const path = require('path');
const glob = require('glob');

const fixturesDir = path.join(__dirname, 'fixtures');
const configFiles = glob.sync('**/*-config.{js,cjs,mjs}', {
  cwd: fixturesDir,
  absolute: true
});
module.exports.configFiles = configFiles;
