const fs = require("fs");
const path = require("path");
const crypto = require("crypto");
const { execSync } = require("child_process");
const glob = require("glob");
const { joinPathPreserveDriveLetter } = require("./utils.cjs");

// Determine the current script directory and project directory
const scriptDir = path.dirname(__filename);
const projectDir = path.dirname(scriptDir);
const envPath = path.join(projectDir, ".env");

// Load the .env file if it exists
if (fs.existsSync(envPath)) {
  const envContent = fs.readFileSync(envPath, "utf-8");
  envContent.split("\n").forEach((line) => {
    if (line && !line.startsWith("#")) {
      const [key, value] = line.split("=");
      process.env[key.trim()] = value.trim();
    }
  });
  console.log(`${envPath} file loaded`);
}

// Define the output file
const relativeOutputFile = ".husky/hash.txt";
const outputFile = path.join(projectDir, relativeOutputFile);

// Create or clear the hash file
fs.writeFileSync(outputFile, "");

// List of file extensions to include
const extensions = ["py", "js", "php", "cjs", "mjs"];

// Directories to exclude
const excludeDirs = [
  "dashboard/",
  "bin/",
  "node_modules/",
  "vendor/",
  "venv/",
  ".yarn/",
  "__pycache__/",
  "docs/",
  "xl/",
  "django_backend/",
  "userscripts/",
  "webalizer/",
  ".cache/",
  "tests/",
  "example",
  ".husky/",
  "packages/",
  "dist/",
  "tmp/"
]
  .map((pattern) => joinPathPreserveDriveLetter(projectDir, pattern))
  .concat([".vscode"]);

// Convert EXCLUDE_DIRS array to a glob pattern
const excludePattern = `**/@(${excludeDirs.join("|")})/**`;

// Initialize an array to hold the formatted outputs
let hashArray = []; //fs.readFileSync(outputFile, "utf-8").split(/\r?\n/);

// Find files with the specified extensions, compute their hash, and save to HASH_ARRAY
extensions.forEach((ext) => {
  const files = glob.sync(`**/*.${ext}`, {
    cwd: projectDir,
    ignore: excludePattern,
    absolute: true
  });

  const filter = files.filter((file) => !excludeDirs.some((prefix) => file.startsWith(prefix)));
  filter.forEach((file) => {
    const fileBuffer = fs.readFileSync(file);
    const hash = crypto.createHash("sha256").update(fileBuffer).digest("hex");
    const relativePath = path.relative(projectDir, file);
    const formattedOutput = `${relativePath} ${hash}`;
    hashArray.push(formattedOutput);
  });
});

// Sort the hashArray by file paths
hashArray.sort((a, b) => a.localeCompare(b));

// Output the sorted hashes to the file
hashArray
  .filter((item) => item.length > 10)
  .forEach((item) => {
    console.log(item);
    fs.appendFileSync(outputFile, `${item}\n`);
  });

// Add the hash file to the commit
execSync(`git add ${relativeOutputFile}`);
