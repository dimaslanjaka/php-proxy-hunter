import { execSync } from 'child_process';

export function getGitHistory() {
  // Use a clear separator for commits
  const SEP = '---end---';
  const log = execSync(`git log --pretty=format:%H%n%B%n${SEP} --name-only`, { encoding: 'utf8' });
  const lines = log.split(/\r?\n/);
  const commits: Array<{ hash: string; message: string; files: string[] }> = [];
  let hash = '';
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
          message: messageLines.join('\n').trim(),
          files: files.filter((f) => f !== '.husky/hash.txt')
        });
      }
      hash = line;
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
      message: messageLines.join('\n').trim(),
      files: files.filter((f) => f !== '.husky/hash.txt')
    });
  }
  return commits;
}
