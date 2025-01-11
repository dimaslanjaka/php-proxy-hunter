import { FOLDER_CONFIG, FOLDER_LOG } from '../Function.js';
import waConnect from '../waConnect.js';

const con = new waConnect();
con.setup({ base: FOLDER_CONFIG, logDir: FOLDER_LOG }).connect();
con.on('messages', async (replier) => {
  console.log(replier.sender);
});
