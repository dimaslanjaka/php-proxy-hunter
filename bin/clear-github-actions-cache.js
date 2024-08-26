const { path } = require("sbg-utility");
require("dotenv").config({ path: path.join(__dirname, "/../") });
const axios = require("axios");
const { exec } = require("child_process");
const { URL } = require("url");
const { promisify } = require("util");

// Promisify exec
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

const ACCESS_TOKEN = process.env.ACCESS_TOKEN;

/**
 * Deletes a GitHub Actions cache.
 * @param {string} ghRepo - The GitHub repository in the format "owner/repo".
 * @param {string} cacheId - The ID of the cache to delete.
 * @returns {Promise} - A promise that resolves when the cache is deleted or rejects with an error.
 */
function deleteGitHubActionsCache(ghRepo, cacheId) {
  const url = `https://api.github.com/repos/${ghRepo}/actions/caches/${cacheId}`;

  return axios
    .delete(url, {
      headers: {
        Authorization: `token ${process.env.ACCESS_TOKEN}`,
        Accept: "application/vnd.github.v3+json"
      }
    })
    .then((response) => {
      console.log("Cache deleted successfully:", response.data);
      return response.data;
    })
    .catch((error) => {
      console.error("Error deleting cache:", error.response ? error.response.data : error.message);
      throw error;
    });
}

/**
 * list github actions caches
 * @param {string} GH_REPO
 * @returns {Promise<Record<string, any>[]>}
 */
function get_caches(GH_REPO) {
  const url = `https://api.github.com/repos/${GH_REPO}/actions/caches`;

  return new Promise((resolve, reject) => {
    axios
      .get(url, {
        headers: {
          Accept: "application/vnd.github.v3+json",
          Authorization: `token ${ACCESS_TOKEN}`
        }
      })
      .then((response) => {
        /**
         * @type {Record<string, any>[]}
         */
        const data = response.data.actions_caches;
        // resolve(response.data);
        // Function to extract the prefix from the key
        const getPrefix = (key) => {
          // Adjust the logic as per your prefix definition
          return key.split("-")[0];
        };

        // Group by prefix
        const grouped = data.reduce(
          /**
           * @param {Record<string, Record<string, any>[]>} acc
           * @param {Record<string, any>} item
           * @returns
           */
          (acc, item) => {
            const prefix = getPrefix(item.key);

            if (!acc[prefix]) {
              acc[prefix] = [];
            }

            acc[prefix].push(item);

            return acc;
          },
          {}
        );

        // Convert the grouped object into an array of arrays
        const result = Object.values(grouped);
        resolve(result);
      })
      .catch((error) => {
        console.error("Error fetching data:", error);
        reject(error); // Reject the promise with the error
      });
  });
}

parseGitRemotes().then((remotes) => {
  const GH_REPO = remotes.origin;
  get_caches(GH_REPO).then((caches) => {
    caches.forEach((item) => {
      console.log(item);
    });
  });
});
