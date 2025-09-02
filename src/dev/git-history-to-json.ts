import { execSync } from 'child_process';

/**
 * Options for filtering git history.
 *
 * @property since Only include commits after this date (YYYY-MM-DD).
 * @property until Only include commits before this date (YYYY-MM-DD).
 * @property last  Only include the last N commits.
 */
export interface GitHistoryOptions {
  /** e.g. '2024-01-01' */
  since?: string;
  /** e.g. '2024-12-31' */
  until?: string;
  /** e.g. 10 (last 10 commits) */
  last?: number;
}

const bannedFiles = [/\.(husky|git)\//];
const anonymizedFiles = {
  '--isimple--': /\/isimple\//,
  '--sidompul--': /\/sidompul\//,
  '--im3--': /\/im3\//,
  '--xl--': /\/xl\//,
  '--axis--': /\/axis\//
};

/**
 * Get git commit history with hash, date, message, and changed files.
 *
 * @param options - Filter options for git history. See {@link GitHistoryOptions}.
 * @returns Array of commit objects: { hash, date, message, files[] }
 *
 * @example
 * // Get last 10 commits
 * getGitHistory({ last: 10 });
 *
 * // Get commits between two dates
 * getGitHistory({ since: '2024-01-01', until: '2024-12-31' });
 */
export function gitHistoryToJson(options: GitHistoryOptions = {}) {
  // Use a clear separator for commits
  const SEP = '---end---';
  let rangeArgs = '';
  if (options.since) rangeArgs += ` --since="${options.since}"`;
  if (options.until) rangeArgs += ` --until="${options.until}"`;
  if (options.last) rangeArgs += ` -n ${options.last}`;
  // Add %cI (ISO 8601 commit date) after hash
  const log = execSync(`git log${rangeArgs} --pretty=format:%H%n%cI%n%B%n${SEP} --name-only`, { encoding: 'utf8' });
  const lines = log.split(/\r?\n/);
  const commits: Array<{ hash: string; date: string; message: string; files: string[] }> = [];
  let hash = '';
  let date = '';
  let messageLines: string[] = [];
  let files: string[] = [];
  let inMessage = false;
  let inFiles = false;
  for (let i = 0; i < lines.length; i++) {
    const line = lines[i];
    if (/^[a-f0-9]{40}$/.test(line)) {
      // New commit hash
      if (hash) {
        commits.push({
          hash,
          date,
          message: messageLines.join('\n').trim(),
          files: files
            .filter((f) => !bannedFiles.some((pattern) => pattern.test(f)))
            .map((f) => {
              // Anonymize file paths
              for (const [key, pattern] of Object.entries(anonymizedFiles)) {
                if (pattern.test(f)) {
                  return f.replace(pattern, key);
                }
              }
              return f;
            })
        });
      }
      hash = line;
      date = lines[++i] || '';
      messageLines = [];
      files = [];
      inMessage = true;
      inFiles = false;
      continue;
    }
    if (line === SEP) {
      // End of commit message, next lines are files
      inMessage = false;
      inFiles = true;
      continue;
    }
    if (inMessage) {
      messageLines.push(line);
    } else if (inFiles && line.trim() !== '') {
      files.push(line.trim());
    }
  }
  // Push last commit if present
  if (hash) {
    commits.push({
      hash,
      date,
      message: messageLines.join('\n').trim(),
      files: files.filter((f) => f !== '.husky/hash.txt')
    });
  }
  return commits;
}

export default gitHistoryToJson;
