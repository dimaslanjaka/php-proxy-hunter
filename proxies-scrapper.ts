import axios from "axios";
import { proxyGrabber } from "proxies-grabber";
import { fs, path } from "sbg-utility";

const grabber = new proxyGrabber();
grabber.get().then(function (proxies) {
  const result = JSON.stringify(proxies);
  // Custom cookies
  const cookies = {
    __ga: "value_of__ga_cookie",
    _ga: "value_of__ga_cookie"
  };

  // Convert cookies object to string
  const cookieString = Object.entries(cookies)
    .map(([key, value]) => `${key}=${value}`)
    .join("; ");

  axios
    .post("http://sh.webmanajemen.com/proxyAdd.php", new URLSearchParams({ proxies: result }), {
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

  fs.appendFileSync(path.join(__dirname, "proxies.txt"), "\n" + result);
});
