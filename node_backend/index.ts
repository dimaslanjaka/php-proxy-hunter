import path from 'upath';
import { loadModule } from './Function.js';
import waConnect from './waConnect.js';
import { fileURLToPath } from 'url';
import { dirname } from 'path';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);
const con = new waConnect();

con.on('messages', async (replier) => {
  const text = replier.receivedText?.trim();
  console.log('Received message:', text);

  const modules = ['ts', 'js']
    .map((ext) => {
      return [
        path.join(__dirname, 'whatsapp_handlers', 'xl.' + ext),
        path.join(__dirname, 'whatsapp_handlers', 'proxy.' + ext)
      ];
    })
    .flat();
  for (let i = 0; i < modules.length; i++) {
    const modulePath = modules[i];
    loadModule(modulePath)
      .then((mod) => {
        if (mod && mod.default) mod.default(replier);
      })
      .catch(console.error);
  }
});

con.setup().connect().catch(console.error);
