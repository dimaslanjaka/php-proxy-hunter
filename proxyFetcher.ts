import axios from "axios";
import fs from "fs-extra";
import { proxyGrabber } from "proxies-grabber";
import { path } from "sbg-utility";

const grabber = new proxyGrabber();
grabber.get().then(function (proxies) {
  const result = JSON.stringify(proxies, null, 2);
  // Custom cookies
  const cookies = {
    __ga: "value_of__ga_cookie",
    _ga: "value_of__ga_cookie"
  };

  // Convert cookies object to string
  const cookieString = Object.entries(cookies)
    .map(([key, value]) => `${key}=${value}`)
    .join("; ");

  const lines = splitStringByLines(result);
  lines.forEach((line) => {
    axios
      .post("http://sh.webmanajemen.com/proxyAdd.php", new URLSearchParams({ proxies: line }), {
        withCredentials: true,
        headers: {
          Cookie: cookieString
        }
      })
      .then((res) => {
        console.log(res.data);
      })
      .catch(() => {
        //
      });
  });

  fs.appendFileSync(path.join(__dirname, "proxies.txt"), "\n" + result);
});

function splitStringByLines(inputString: string, linesPerChunk = 500) {
  // Split the input string into an array of lines
  const lines = inputString.split("\n");

  // Initialize the result array
  const chunks = [];

  // Loop through the lines and create chunks
  for (let i = 0; i < lines.length; i += linesPerChunk) {
    // Get a chunk of the specified number of lines
    const chunk = lines.slice(i, i + linesPerChunk);

    // Join the chunk back into a string and push it to the chunks array
    chunks.push(chunk.join("\n"));
  }

  return chunks;
}
