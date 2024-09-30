import axios from "axios";
import Bluebird from "bluebird";
import { app, BrowserWindow } from "electron";
import fs from "fs";
import path from "path";
import { md5 } from "sbg-utility";

// set timezone
process.env.TZ = "America/Los_Angeles";

app.whenReady().then(async () => {
  const proxies = readFile(path.join(__dirname, "proxies.txt"))
    .map((s) => s.trim())
    .filter((s) => s.length > 7);
  const workingProxies = [] as string[];
  for (let i = 0; i < proxies.length; i++) {
    const proxy = proxies[i];
    const check = await checkProxy("http://" + proxy);
    if (check) workingProxies.push(proxy);
  }

  console.log("total working proxies", workingProxies.length);

  // if (check) {
  //   const geo = await getLocation(proxy).catch((_) => null);
  //   if (geo && geo.length > 0) {
  //     console.log(geo);
  //   }
  // }
});

function readFile(filePath: string): string[] {
  // Read the file synchronously
  const data: string = fs.readFileSync(filePath, "utf-8");
  // Split the content by lines
  const lines: string[] = data.split(/\r?\n/);
  return lines;
}

/**
 * check proxy using electron
 * @param proxy http://ip:port socks://ip:port
 */
function checkProxy(proxy: string): Bluebird<boolean> {
  return new Bluebird((resolve) => {
    const mainWindow = new BrowserWindow({
      width: 800,
      height: 600,
      webPreferences: {
        // Set your Electron web preferences here
        sandbox: false, // Example: This corresponds to --no-sandbox
        enableBlinkFeatures: "IdleDetection" // Example: This corresponds to --enable-blink-features=IdleDetection
      }
    });
    if (mainWindow.minimizable) mainWindow.minimize();
    // 213.131.230.22:3128
    mainWindow.webContents.session.setUserAgent(
      "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_5) AppleWebKit/600.8.9 (KHTML, like Gecko) Version/8.0.8 Safari/600.8.9"
    );

    // set window proxy
    mainWindow.webContents.session.setProxy({ proxyRules: proxy });

    // Set user data directory
    app.setPath("userData", path.join(process.cwd(), "tmp/profiles/default"));

    // Set proxy server
    app.commandLine.appendSwitch("proxy-server", proxy);

    // Set other command line switches
    app.commandLine.appendSwitch("force-color-profile", "srgb");
    app.commandLine.appendSwitch("metrics-recording-only");
    app.commandLine.appendSwitch("no-first-run");
    // Add other command line switches as needed

    // Load your app's HTML file
    mainWindow.loadURL("https://proxy6.net/en/privacy");
    // mainWindow.loadURL('https://bing.com');

    mainWindow.webContents.on("did-fail-load", (_event, errorCode, errorDescription, validatedURL) => {
      if (["ERR_PROXY_CONNECTION_FAILED"].includes(errorDescription)) {
        console.error(proxy, "not working, proxy failed");
        moveString(path.join(__dirname, "proxies.txt"), path.join(__dirname, "dead.txt"), proxy.replace("http://", ""));
      } else if (errorDescription == "ERR_CERT_AUTHORITY_INVALID") {
        console.error(proxy, "not working, Non-SSL");
        moveString(path.join(__dirname, "proxies.txt"), path.join(__dirname, "dead.txt"), proxy.replace("http://", ""));
      } else {
        console.error(`Failed to load URL: ${validatedURL}`);
        console.error(`Error code: ${errorCode}, Description: ${errorDescription}`);
        console.error(proxy, "not working, fail load page");
      }

      if (mainWindow.closable) mainWindow.close();
      resolve(false);
    });

    mainWindow.webContents.on("did-finish-load", () => {
      let result = false;
      const title = mainWindow.getTitle();
      if (title.includes("Anonymity check")) {
        console.log(proxy, "working");
        result = true;
      } else {
        console.error(proxy, "not working");
        moveString(path.join(__dirname, "proxies.txt"), path.join(__dirname, "dead.txt"), proxy.replace("http://", ""));
      }

      if (mainWindow.closable) mainWindow.close();
      resolve(result);
    });
  });
}

async function getLocation(proxy: string) {
  const [ip, port] = proxy.split(":");
  console.log(`check geo ${proxy} -> ${ip}:${port}`);

  try {
    const url = `http://ip-get-geolocation.com/api/json/${ip}`;
    let response: Record<string, any> = {};
    const cacheFile = path.join(__dirname, ".cache", md5(url));
    const isCached = fs.existsSync(cacheFile);
    const proxyConfig = {
      host: ip,
      port: parseInt(port)
    };
    if (isCached) {
      response = { data: JSON.parse(fs.readFileSync(cacheFile, "utf-8")) };
    } else {
      response = await axios
        .get(url, {
          proxy: proxyConfig
        })
        .catch(() => {
          return { data: null };
        });
    }
    const myIP = await axios
      .get("https://api.ipify.org?format=json", {
        proxy: proxyConfig
      })
      .catch(() => {
        return { data: { ip: null } };
      });
    const proxyIpAddress = myIP.data.ip;
    console.log(proxyIpAddress, proxy);

    if (response.data) {
      const locationArray = response.data;
      if (typeof locationArray == "object" && locationArray) {
        if (locationArray.status && locationArray.status == "fail") {
          // max reached
          return "";
        } else if (locationArray.country) {
          const item = `${locationArray.region}|${locationArray.city}|${locationArray.country}|${locationArray.timezone}`;
          // write cache
          if (!isCached) fs.writeFileSync(cacheFile, JSON.stringify(locationArray));
          return item;
        } else {
          console.log(locationArray);
        }
      }
    } else {
      // throw new Error('Empty response');
      return "";
    }
  } catch (error: any) {
    console.error(error.message);
    return ""; // or handle error accordingly
  }
  return "";
}

function moveString(originalFilePath: string, newFilePath: string, searchString: string): void {
  // Read the original file
  let originalContent: string = fs.readFileSync(originalFilePath, "utf8");

  // Find the index of the search string
  const index: number = originalContent.indexOf(searchString);

  if (index === -1) {
    console.log("String not found in original file.");
    return;
  }

  // Extract the string to move
  const movedString: string = originalContent.substring(index, index + searchString.length);

  // Append the string to the new file
  fs.appendFileSync(newFilePath, movedString + "\n"); // Append to a new line

  // Remove the string from the original content
  originalContent = originalContent.substring(0, index) + originalContent.substring(index + searchString.length);

  // Write back to the original file
  fs.writeFileSync(originalFilePath, originalContent);
}
