const { exec } = require("child_process");
const { URL } = require("url");
const { promisify } = require("util");

/** Promisify exec  */
const execAsync = promisify(exec);

async function parseGitRemotes() {
  try {
    // Run the `git remote -v` command
    const { stdout } = await execAsync("git remote -v");

    // Split the output into lines
    const lines = stdout.split("\n");

    // Object to hold the remotes
    const remotes = {};

    // Process each line
    lines.forEach((line) => {
      const [name, url] = line.split("\t");

      if (name && url) {
        const [repoUrl] = url.split(" ");
        try {
          // Parse the URL
          const parsedUrl = new URL(repoUrl);

          // Extract the path from the URL
          const pathParts = parsedUrl.pathname.split("/").filter(Boolean);

          // Check if the URL is from GitHub and has the username/repo format
          if (parsedUrl.hostname === "github.com" && pathParts.length === 2) {
            // Remove the `.git` suffix if present
            let repoPath = pathParts.join("/");
            if (repoPath.endsWith(".git")) {
              repoPath = repoPath.slice(0, -4); // Remove the `.git` suffix
            }
            remotes[name] = repoPath;
          }
        } catch (e) {
          console.error("URL Parsing Error:", e.message);
        }
      }
    });

    return remotes;
  } catch (error) {
    console.error("Error:", error.message);
    return {};
  }
}
/**
 * Joins all given path segments together and normalizes the resulting path.
 * Preserves the case of the drive letter on Windows.
 *
 * @param {...string} segments - The path segments to join.
 * @returns {string} - The normalized path with the drive letter case preserved.
 */
function joinPathPreserveDriveLetter(...segments) {
  let fullPath = require("path").join(...segments);

  // Check if the path starts with a drive letter (e.g., C:\)
  if (/^[a-z]:\\/.test(fullPath)) {
    // Convert the drive letter to uppercase
    fullPath = fullPath.charAt(0).toUpperCase() + fullPath.slice(1);
  }

  return fullPath;
}

module.exports = { parseGitRemotes, execAsync, joinPathPreserveDriveLetter };
