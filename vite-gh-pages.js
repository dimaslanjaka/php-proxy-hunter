import { build, mergeConfig } from 'vite';
import config from './vite.config.js';
import fs from 'fs-extra';
import path from 'upath';
import { fileURLToPath } from 'url';
import { spawnSync } from 'child_process';

/**
 * Fixes __dirname for ESM modules.
 * @type {string}
 */
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

/**
 * Vite configuration merged with GitHub Pages base path.
 * @type {import('vite').UserConfig}
 */
const viteConfig = mergeConfig(config, { base: '/php-proxy-hunter/' });

/**
 * Builds the project for GitHub Pages and deploys it.
 * @returns {Promise<void>}
 */
async function buildForGithubPages() {
  await build(viteConfig)
    .then(() => {
      console.log('Build successful for GitHub Pages');
    })
    .catch(console.error);
  // add .no_jekyll file to prevent Jekyll processing
  const noJekyllPath = path.join(viteConfig.build.outDir, '.no_jekyll');
  if (!fs.existsSync(noJekyllPath)) {
    fs.writeFileSync(noJekyllPath, '');
    console.log('.no_jekyll file created to prevent Jekyll processing');
  }
  // Deploy to .deploy_git directory
  await deploy();
}

/**
 * Deploys the built project to the .deploy_git directory for GitHub Pages.
 * Clones the repository if the directory does not exist.
 * Removes old assets and PHP files, then copies new build output.
 * @returns {Promise<void>}
 */
async function deploy() {
  // Ensure .deploy_git directory exists
  const deployGitPath = path.join(__dirname, '.deploy_git');
  if (!fs.existsSync(deployGitPath)) {
    const gitUrlResult = spawnSync('git', ['config', '--get', 'remote.origin.url'], {
      cwd: __dirname,
      encoding: 'utf-8'
    });
    const gitUrl = gitUrlResult.stdout.trim();
    console.log(`Cloning repository from ${gitUrl} to ${deployGitPath}`);
    spawnSync('git', ['clone', gitUrl, deployGitPath], {
      cwd: __dirname,
      stdio: 'inherit'
    });
  }
  // Delete react auto generated files
  fs.rmSync(path.join(deployGitPath, 'assets'), { recursive: true, force: true });
  // Copy dist/react to .deploy_git directory
  fs.copySync(viteConfig.build.outDir, deployGitPath, { overwrite: true, dereference: true });
  // Delete php assets
  fs.rmSync(path.join(deployGitPath, 'php'), { recursive: true, force: true });
}

// Run the build and deploy process
buildForGithubPages();
