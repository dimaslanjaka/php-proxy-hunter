const { path, noop } = require('sbg-utility');
const gch = require('git-command-helper');
require('dotenv').config({ path: path.join(__dirname, '/../'), override: true });

const GH_TOKEN = process.env.ACCESS_TOKEN;

async function fixRemotes() {
  await gch
    .spawnAsync(
      'git',
      ['remote', 'add', 'origin', `https://${GH_TOKEN}@github.com/dimaslanjaka/php-proxy-hunter.git`],
      { stdio: 'inherit' }
    )
    .then(noop)
    .catch(noop);
  await gch
    .spawnAsync(
      'git',
      ['remote', 'set-url', 'origin', `https://${GH_TOKEN}@github.com/dimaslanjaka/php-proxy-hunter.git`],
      { stdio: 'inherit' }
    )
    .then(noop)
    .catch(noop);
  await gch
    .spawnAsync(
      'git',
      ['remote', 'add', 'private', `https://${GH_TOKEN}@github.com/dimaslanjaka/traffic-generator.git`],
      { stdio: 'inherit' }
    )
    .then(noop)
    .catch(noop);
  await gch
    .spawnAsync(
      'git',
      ['remote', 'set-url', 'private', `https://${GH_TOKEN}@github.com/dimaslanjaka/traffic-generator.git`],
      { stdio: 'inherit' }
    )
    .then(noop)
    .catch(noop);
}

fixRemotes().catch(noop);
