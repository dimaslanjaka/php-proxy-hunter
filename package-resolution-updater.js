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

// 📌 Static override rules
const specialPackageOverrides = [
  { pkg: 'sbg-utility', branch: 'sbg-utility', repo: 'static-blog-generator', owner: 'dimaslanjaka' },
  { pkg: 'sbg-api', branch: 'sbg-api', repo: 'static-blog-generator', owner: 'dimaslanjaka' },
  { pkg: 'instant-indexing', branch: 'instant-indexing', repo: 'static-blog-generator', owner: 'dimaslanjaka' }
];

/**
 * Fetch JSON from a URL with GitHub headers.
 * @param {string} url
 * @returns {Promise<any>}
 */
function fetchJson(url) {
  const headers = {
    'User-Agent': pkg.name || 'node.js',
    Accept: 'application/vnd.github.v3+json',
    'X-GitHub-Api-Version': '2022-11-28',
    ...(process.env.GITHUB_TOKEN ? { Authorization: `token ${process.env.GITHUB_TOKEN}` } : {})
  };

  return new Promise((resolve, reject) => {
    https
      .get(url, { headers }, (res) => {
        let data = '';
        res.on('data', (chunk) => (data += chunk));
        res.on('end', () => {
          try {
            const json = JSON.parse(data);
            if (res.statusCode < 200 || res.statusCode >= 300) {
              return reject(
                new Error(`GitHub API Error ${res.statusCode}: ${json.message || 'Unknown error'}\nURL: ${url}`)
              );
            }
            resolve(json);
          } catch {
            reject(new Error(`Invalid JSON from: ${url}`));
          }
        });
      })
      .on('error', reject);
  });
}

/**
 * Get latest commit SHA from a specific branch.
 */
async function getLatestCommit(owner, repo, branch = 'main') {
  const url = `https://api.github.com/repos/${owner}/${repo}/commits/${branch}`;
  const json = await fetchJson(url);

  const sha = json.sha;
  const dateStr = json.commit?.committer?.date || json.commit?.author?.date;

  if (!sha || !dateStr) {
    console.log(json);
    throw new Error(`Missing SHA or date for ${owner}/${repo}@${branch}`);
  }

  return {
    owner,
    repo,
    branch,
    sha,
    date: new Date(dateStr).toISOString()
  };
}

/**
 * Get latest commit SHA from all branches and pick the latest.
 */
async function getLatestCommitAcrossBranches(owner, repo) {
  const branches = await fetchJson(`https://api.github.com/repos/${owner}/${repo}/branches`);

  const commits = await Promise.all(
    branches.map(async ({ name, commit }) => {
      const commitSha = commit?.sha;
      if (!commitSha) {
        console.warn(`No commit SHA for '${owner}/${repo}' branch: ${name}`);
        return { branch: name, sha: '', date: new Date(0) };
      }

      try {
        const commitData = await fetchJson(`https://api.github.com/repos/${owner}/${repo}/commits/${commitSha}`);
        const dateStr = commitData.commit?.committer?.date || commitData.commit?.author?.date;
        const date = dateStr ? new Date(dateStr) : new Date(0);
        return { branch: name, sha: commitData.sha, date };
      } catch (e) {
        console.warn(`Failed to fetch commit for ${name}: ${e.message}`);
        return { branch: name, sha: commitSha, date: new Date(0) };
      }
    })
  );

  const latest = commits.reduce((a, b) => (a.date > b.date ? a : b), { date: new Date(0) });

  return {
    owner,
    repo,
    branch: latest.branch,
    sha: latest.sha,
    date: latest.date.toISOString()
  };
}

/**
 * Replace the branch or commit in a GitHub raw URL with the latest hash.
 */
function replaceRawWithLatestHash(url, latestHash) {
  const match = url.match(/^https:\/\/github\.com\/([^/]+)\/([^/]+)\/raw\/([^/]+)\/(.+)$/);
  if (!match) throw new Error('Invalid GitHub raw URL');

  const [, owner, repo, _oldHash, path] = match;
  return `https://github.com/${owner}/${repo}/raw/${latestHash}/${path}`;
}

/**
 * Parse GitHub URLs and extract owner, repo, branch, and original URL.
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

// 🧠 Main logic
(async () => {
  const entries = Object.entries(pkg.resolutions || {});
  const updates = await Promise.all(
    entries.map(async ([currentPkgName, url]) => {
      const repo = parseGitHubUrl(url);
      const override = specialPackageOverrides.find((p) => p.pkg === currentPkgName);

      const latest = override
        ? await getLatestCommit(override.owner, override.repo, override.branch)
        : await getLatestCommitAcrossBranches(repo.owner, repo.repo);

      const new_url = replaceRawWithLatestHash(url, latest.sha);

      return {
        currentPkgName,
        url,
        new_url,
        repo,
        latest
      };
    })
  );

  for (const { currentPkgName, url, new_url, repo, latest } of updates) {
    if (url !== new_url) {
      console.log(`\n${ansiColors.cyan(currentPkgName)}:`);
      console.log('  from:', url.replace(repo.branch, ansiColors.red(repo.branch)));
      console.log('    to:', new_url.replace(latest.sha, ansiColors.green(latest.sha)));
      pkg.resolutions[currentPkgName] = new_url;
    }
  }

  fs.writeFileSync(path.join(__dirname, 'package.json'), JSON.stringify(pkg, null, 2));
})();
