const { execSync } = require("child_process");

// List of packages to check
const packages = ["gulp", "glob", "sbg-utility"];

// Function to check if a package is installed
const isPackageInstalled = (packageName) => {
  try {
    require.resolve(packageName);
    return true;
  } catch (_err) {
    return false;
  }
};

// Check if any package is not installed
const missingPackages = packages.filter((packageName) => !isPackageInstalled(packageName));

if (missingPackages.length > 0) {
  console.log("Some packages are missing:", missingPackages.join(", "));
  console.log("Running yarn install...");
  execSync("yarn install", { stdio: "inherit" });
} else {
  console.log("All packages are installed.");
}
