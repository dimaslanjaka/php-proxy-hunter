import { execSync } from 'child_process';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { fixturesDir } from './env.cjs';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

export function runCli(cwd, timeout = 30000) {
  const buildScriptPath = path.join(__dirname, '../bin/build-gh-pages.js');

  try {
    // Run the build script from the project root directory
    const output = execSync(`node "${buildScriptPath}"`, {
      encoding: 'utf-8',
      cwd,
      timeout
    });
    return output;
  } catch (error) {
    throw new Error(`Run cli failed: ${error.message}`);
  }
}

export function populateMarkdownFiles() {
  const repoUrl = 'https://github.com/frontendweb3/Demo-markdown-posts.git';
  const targetDir = path.join(fixturesDir, 'posts');

  if (!fs.existsSync(targetDir) && !fs.existsSync(path.join(targetDir, '.git'))) {
    try {
      execSync(`git clone ${repoUrl} "${targetDir}"`, { stdio: 'inherit' });
      console.log(`Repository cloned to ${targetDir}`);
    } catch (error) {
      console.error(`Error cloning repo: ${error.message}`);
    }
  }
}
