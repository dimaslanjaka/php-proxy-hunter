import fs from 'fs-extra';
import * as glob from 'glob';
import { writefile } from 'sbg-utility';
import path from 'upath';
import { fileURLToPath } from 'url';
import { getWhatsappFile, loadModule, whatsappLogger } from './Function.js';
import waConnect from './waConnect.js';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const con = new waConnect();
const logDir = getWhatsappFile('tmp/logs');
const handlersDir = path.join(__dirname, 'whatsapp_handlers');

// Ensure log directory exists
fs.ensureDirSync(logDir);

// Reset existing log files
const resetMessage = `Log reset at ${new Date().toISOString()}\n\n`;
const logFiles = [
  ...glob.globSync('**/whatsapp*.log', { cwd: logDir, absolute: true }),
  path.join(logDir, 'whatsapp-error.log'),
  path.join(logDir, 'whatsapp.log')
];
await Promise.all(logFiles.map((file) => writefile(file, resetMessage)));

// Set to track active sender names
const activeSenders = new Set();

con.on('messages', async (replier) => {
  const senderName = replier.senderId;
  const text = replier.receivedText?.trim();
  if (replier.isGroup) return; // Ignore group messages

  console.log(`[WA] ${new Date().toISOString()} - From: ${senderName}, Text: "${text}"`);

  if (activeSenders.has(senderName)) {
    replier.reply('Please wait for the previous process to complete.');
    return;
  }

  activeSenders.add(senderName);

  try {
    const modules = glob.globSync('*.{js,ts,cjs,mjs}', {
      cwd: handlersDir,
      absolute: true
    });

    for (const modulePath of modules) {
      try {
        const mod = await loadModule(modulePath);
        if (mod?.default) {
          await mod.default(replier);
        }
      } catch (e) {
        console.error(`[Handler Error] in ${modulePath}:`, e);
        whatsappLogger.error({ err: e });
      }
    }
  } catch (e) {
    whatsappLogger.error({ err: e });
  } finally {
    activeSenders.delete(senderName);
  }
});

con
  .setup()
  .connect()
  .catch((e) => whatsappLogger.error({ err: e }));
