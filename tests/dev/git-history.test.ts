import { getGitHistory } from '../../src/dev/git-history-to-json';

jest.setTimeout(100000); // Set a longer timeout for git operations

describe('getGitHistory', () => {
  it('should return an array of commits with hash, message, and files', () => {
    const commits = getGitHistory({ last: 5 });
    expect(Array.isArray(commits)).toBe(true);
    expect(commits.length).toBeGreaterThan(0);
    for (const commit of commits) {
      expect(typeof commit.hash).toBe('string');
      expect(commit.hash.length).toBe(40);
      expect(typeof commit.message).toBe('string');
      expect(Array.isArray(commit.files)).toBe(true);
      // .husky/hash.txt should not be present
      expect(commit.files.includes('.husky/hash.txt')).toBe(false);
    }
  });

  it('should filter by date range', () => {
    // Use a wide range to ensure at least one commit
    const commits = getGitHistory({ since: '2000-01-01', until: '2100-01-01' });
    expect(Array.isArray(commits)).toBe(true);
    expect(commits.length).toBeGreaterThan(0);
  });

  it('should return only the last N commits', () => {
    const n = 3;
    const commits = getGitHistory({ last: n });
    expect(commits.length).toBeLessThanOrEqual(n);
  });
});
