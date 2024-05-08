import proxyGrabber from "proxies-grabber";

const grabber = new proxyGrabber();
grabber.get().then(function(proxies) {
  console.log(proxies);
});
