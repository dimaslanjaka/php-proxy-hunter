import { FOLDER_CONFIG, FOLDER_LOG } from '../Function.js';
import waConnect from '../waConnect.js';

const con = new waConnect();
con.setup({ base: FOLDER_CONFIG, logDir: FOLDER_LOG }).connect();
con.once('sync', () => {
  setTimeout(async () => {
    con.getReplier('+6285655667573@s.whatsapp.net').reply('hiosin');
  }, 3000);
});
