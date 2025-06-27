/**
 * 📦 GitHub Package Resolver
 *
 * This script updates the commit hashes in `package.json`'s `resolutions` field
 * for GitHub tarball URLs (typically using `raw/branch-name/...`) to point to the
 * latest commit SHA of the corresponding repository and branch.
 *
 * 🔍 Features:
 * - Parses GitHub URLs to extract repository owner, name, and branch.
 * - Fetches the latest commit SHA across all branches using GitHub's API.
 * - Replaces the old branch or commit in the URL with the latest SHA.
 * - Overwrites `package.json` with the updated URLs.
 *
 * 🛠 Requirements:
 * - GitHub Personal Access Token (GITHUB_TOKEN) via `.env`
 * - ESM support (`type: "module"` in `package.json`)
 * - Node.js v18+ recommended for ESM and `fetch` fallback compatibility
 *
 * 🧩 Dependencies:
 * - `ansi-colors` – for styled terminal output
 * - `dotenv` – to load GitHub token from `.env`
 *
 * ✅ Use case:
 * - Ensures package resolutions always use immutable SHAs instead of mutable branch names.
 * - Helps achieve deterministic builds in monorepos or projects with internal GitHub packages.
 */

import ansiColors from 'ansi-colors';
import 'dotenv/config';
import fs from 'fs';
import https from 'https';
import path from 'path';
import { fileURLToPath } from 'url';
import pkg from './package.json' with { type: 'json' };

const __dirname = path.dirname(fileURLToPath(import.meta.url));

/**
 * Get JSON from a URL with GitHub headers
 */
function fetchJson(url) {
  return new Promise((resolve, reject) => {
    https
      .get(
        url,
        {
          headers: {
            'User-Agent': pkg.name || 'node.js',
            Accept: 'application/vnd.github.v3+json',
            Authorization: `token ${process.env.GITHUB_TOKEN}`
          }
        },
        (res) => {
          let data = '';
          res.on('data', (chunk) => (data += chunk));
          res.on('end', () => {
            try {
              resolve(JSON.parse(data));
            } catch {
              reject(new Error(`Invalid JSON from: ${url}`));
            }
          });
        }
      )
      .on('error', reject);
  });
}

/**
 * Find the branch with the latest commit
 * @param {string} owner
 * @param {string} repo
 * @returns {Promise<{owner: string, repo: string, branch: string, sha: string, date: string}>}
 */
export async function getLatestCommitAcrossBranches(owner, repo) {
  const branches = await fetchJson(`https://api.github.com/repos/${owner}/${repo}/branches`);

  const commits = await Promise.all(
    branches.map(async (branch) => {
      const commitSha = branch?.commit?.sha;
      if (!commitSha) {
        console.warn(`No commit SHA for '${owner}/${repo}' branch: ${branch.name}`);
        return {
          branch: branch.name,
          sha: '',
          date: new Date(0)
        };
      }

      try {
        const commit = await fetchJson(`https://api.github.com/repos/${owner}/${repo}/commits/${commitSha}`);
        const date = commit.commit?.committer?.date || commit.commit?.author?.date;

        if (!date) {
          console.warn(`No commit date found for branch: ${branch.name}`);
        }

        return {
          branch: branch.name,
          sha: commit.sha,
          date: new Date(date)
        };
      } catch (e) {
        console.warn(`Failed to fetch commit for ${branch.name}:`, e.message);
        return {
          branch: branch.name,
          sha: commitSha,
          date: new Date(0)
        };
      }
    })
  );

  const latest = commits.reduce((a, b) => (a.date > b.date ? a : b));

  return {
    owner,
    repo,
    branch: latest.branch,
    sha: latest.sha,
    date: latest.date.toISOString()
  };
}

/**
 * Get the latest commit hash from a GitHub repository's branch.
 * @param {string} owner - GitHub username or organization.
 * @param {string} repo - Repository name.
 * @param {string} branch - Branch name (default: 'main').
 * @returns {Promise<string>} Latest commit SHA.
 */
export async function getLatestCommitHash(owner, repo, branch = 'main') {
  const url = `https://api.github.com/repos/${owner}/${repo}/commits/${branch}`;
  return new Promise((resolve, reject) => {
    https
      .get(
        url,
        {
          headers: {
            'User-Agent': 'node.js',
            Accept: 'application/vnd.github.v3+json'
          }
        },
        (res) => {
          let data = '';
          res.on('data', (chunk) => (data += chunk));
          res.on('end', () => {
            try {
              const json = JSON.parse(data);
              resolve(json.sha);
            } catch (_e) {
              reject(new Error('Failed to parse GitHub API response'));
            }
          });
        }
      )
      .on('error', reject);
  });
}

/**
 * Replace the commit hash in a GitHub raw URL with the latest hash from the repo.
 * @param {string} url - Original GitHub raw URL.
 * @param {string} latestHash - Latest commit hash to use in the new URL.
 * @returns {string} Updated URL with latest commit hash.
 */
export function replaceRawWithLastestHash(url, latestHash) {
  const match = url.match(/^https:\/\/github\.com\/([^/]+)\/([^/]+)\/raw\/([^/]+)\/(.+)$/);
  if (!match) throw new Error('Invalid GitHub raw URL');

  const [, owner, repo, _oldHash, path] = match;
  return `https://github.com/${owner}/${repo}/raw/${latestHash}/${path}`;
}

/**
 * Parse various GitHub URLs to extract owner, repo, branch, and include original URL
 * @param {string} url
 * @returns {{ owner: string, repo: string, branch?: string, url: string }}
 */
function parseGitHubUrl(url) {
  const ghRepoRoot = /^https:\/\/github\.com\/([^/]+)\/([^/]+)\/?$/;
  const ghTreeOrBlob = /^https:\/\/github\.com\/([^/]+)\/([^/]+)\/(tree|blob)\/([^/]+(?:\/[^/]+)*)/;
  const ghRaw = /^https:\/\/raw\.githubusercontent\.com\/([^/]+)\/([^/]+)\/([^/]+)(\/.+)?$/;
  const ghDotComRaw = /^https:\/\/github\.com\/([^/]+)\/([^/]+)\/raw\/([^/]+)\/.+/;

  let match;

  if ((match = url.match(ghRaw))) {
    const [, owner, repo, branch] = match;
    return { owner, repo, branch, url };
  }

  if ((match = url.match(ghDotComRaw))) {
    const [, owner, repo, branch] = match;
    return { owner, repo, branch, url };
  }

  if ((match = url.match(ghTreeOrBlob))) {
    const [, owner, repo, , branchPath] = match;
    return { owner, repo, branch: branchPath, url };
  }

  if ((match = url.match(ghRepoRoot))) {
    const [, owner, repo] = match;
    return { owner, repo, url };
  }

  throw new Error(`Unsupported GitHub URL: ${url}`);
}

(async () => {
  for (const key in pkg.resolutions) {
    if (Object.prototype.hasOwnProperty.call(pkg.resolutions, key)) {
      const url = pkg.resolutions[key];
      const repo = parseGitHubUrl(url);
      const latest = await getLatestCommitAcrossBranches(repo.owner, repo.repo);
      const result = { pkg: key, ...repo, ...latest, new_url: replaceRawWithLastestHash(url, latest.sha) };
      if (url !== result.new_url) {
        console.log(`\n${ansiColors.cyan(result.pkg)}:`); // show package name first
        console.log('  from:', url.replace(repo.branch, ansiColors.red(repo.branch)));
        console.log('    to:', result.new_url.replace(latest.sha, ansiColors.green(latest.sha)));
        pkg.resolutions[key] = result.new_url;
      }
    }
  }
  fs.writeFileSync(path.join(__dirname, 'package.json'), JSON.stringify(pkg, null, 2));
})();
