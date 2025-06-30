import { exec } from 'child_process';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const repoUrl = 'https://github.com/frontendweb3/Demo-markdown-posts.git';
const targetDir = path.join(__dirname, 'posts');

if (!fs.existsSync(targetDir) && !fs.existsSync(path.join(targetDir, '.git'))) {
  exec(`git clone ${repoUrl} "${targetDir}"`, (error, stdout, stderr) => {
    if (error) {
      console.error(`Error cloning repo: ${error.message}`);
      return;
    }
    if (stderr) {
      console.error(`stderr: ${stderr}`);
      return;
    }
    console.log(`Repository cloned to ${targetDir}`);
  });
}
