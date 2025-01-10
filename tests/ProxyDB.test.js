import { getFromProject } from '../.env.mjs';
import ProxyDB from '../src/ProxyDB.js';

const db = new ProxyDB(getFromProject('src/database.sqlite'), true);
// db.db.select("proxies").then((data) => {
//   console.log("got", data.length, "proxies");
// });
// db.getWorkingProxies().then(console.log);
db.getUntestedProxies().then(console.log);
