const { path, noop } = require("sbg-utility");
const gch = require("git-command-helper");
require("dotenv").config({ path: path.join(__dirname, "/../"), override: true });
const { execAsync } = require("./utils");

const GH_TOKEN = process.env.ACCESS_TOKEN;

async function fixRemotes() {
  await execAsync(`git remote add origin https://dimaslanjaka:${GH_TOKEN}@github.com/dimaslanjaka/php-proxy-hunter.git`)
    .then(noop)
    .catch(noop);
  await execAsync(
    `git remote set-url origin https://dimaslanjaka:${GH_TOKEN}@github.com/dimaslanjaka/php-proxy-hunter.git`
  )
    .then(noop)
    .catch(noop);
  await execAsync(
    `git remote add private https://dimaslanjaka:${GH_TOKEN}@github.com/dimaslanjaka/traffic-generator.git`
  )
    .then(noop)
    .catch(noop);
  await execAsync(
    `git remote set-url private https://dimaslanjaka:${GH_TOKEN}@github.com/dimaslanjaka/traffic-generator.git`
  )
    .then(noop)
    .catch(noop);
}

async function pull() {
  await gch.spawnAsync("git", ["fetch", "--all"], { stdio: "inherit" });
  await gch.spawnAsync("git", ["pull", "private", "python"], {
    stdio: "inherit"
  });
  await gch.spawnAsync("git", ["merge", "origin/master", "-X", "theirs", "--no-edit"], {
    stdio: "inherit"
  });
  await gch.spawnAsync("git", ["pull", "private", "python", "-X", "ours", "--no-edit"], {
    stdio: "inherit"
  });
  // await execAsync("git fetch --all");
  // await execAsync("git merge origin/master -X theirs --no-edit");
  // await execAsync("git pull private python -X ours --no-edit");
}

fixRemotes().then(pull);
