const { path } = require("sbg-utility");
require("dotenv").config({ path: path.join(__dirname, "/../"), override: true });
const axios = require("axios");
const { parseGitRemotes } = require("./utils.cjs");

// delete caches leaving single last cache based on creation date

//

const ACCESS_TOKEN = process.env.ACCESS_TOKEN;

/**
 * Deletes a GitHub Actions cache.
 * @param {string} GH_REPO - The GitHub repository in the format "owner/repo".
 * @param {string} cacheId - The ID of the cache to delete.
 * @returns {Promise} - A promise that resolves on success and rejects on error.
 */
function deleteGitHubActionsCache(GH_REPO, cacheId) {
  return new Promise((resolve, reject) => {
    const url = `https://api.github.com/repos/${GH_REPO}/actions/caches/${cacheId}`;
    const token = process.env.ACCESS_TOKEN;

    if (!token) {
      return reject(new Error("Access token is not provided"));
    }

    axios
      .delete(url, {
        headers: {
          Authorization: `token ${token}`,
          Accept: "application/vnd.github.v3+json"
        }
      })
      .then((response) => {
        console.log(`Cache (${cacheId}) deleted successfully`, response.data);
        resolve(response.data); // Resolve with the response data
      })
      .catch((error) => {
        console.error("Error deleting cache:", error.response?.data || error.message || "Unknown error");
        reject(error); // Reject with the error
      });
  });
}

/**
 * list github actions caches
 * @param {string} GH_REPO
 * @returns {Promise<Record<string, Record<string, any>[]>>}
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
        /**
         * extract the prefix from the key
         * @param {string} key
         * @returns
         */
        const getPrefix = (key) => {
          const split = key.split(/[-_]/);
          if (split.length == 3) {
            return `${split[0]}-${split[1]}`;
          } else if (split.length > 3) {
            return `${split[0]}-${split[1]}-${split[2]}`;
          }
          return split[0];
        };

        // Group by prefix
        const grouped = data.reduce(
          /**
           * @param {Record<string, Record<string, any>[]>} acc
           * @param {Record<string, any>} item
           * @returns {Record<string, Record<string, any>[]>}
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
        // const result = Object.values(grouped);
        resolve(grouped);
      })
      .catch((error) => {
        console.error("Error fetching data:", error);
        reject(error); // Reject the promise with the error
      });
  });
}

parseGitRemotes()
  .then((remotes) => {
    const GH_REPO = remotes.origin;
    get_caches(GH_REPO)
      .then((caches) => {
        for (const key in caches) {
          if (Object.hasOwnProperty.call(caches, key)) {
            const item = caches[key]
              .sort((a, b) => new Date(b.created_at) - new Date(a.created_at)) // sort descending orders
              .map((item) => ({
                ...item,
                human_readable_date: new Date(item.created_at).toLocaleString() // human readable format
              }));
            if (item.length > 1) {
              const ids = item.map((o) => o.id);
              ids.shift(); // remove first item
              // console.log(key, ids, item);
              if (ids.length > 0) {
                ids.forEach((id) => deleteGitHubActionsCache(GH_REPO, id));
              } else {
                console.log(`cache prefix ${key} no cache left`);
              }
            } else {
              console.log(`cache prefix ${key} only have 1 cache`);
            }
          }
        }
      })
      .catch((e) => console.error(`fail get caches ${GH_REPO}: ${e}`));
  })
  .catch((e) => console.error(`fail get remotes: ${e}`));
