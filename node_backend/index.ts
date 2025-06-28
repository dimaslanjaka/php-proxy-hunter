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

// Reset existing log files on start
if (!fs.existsSync(logDir)) {
  fs.mkdirSync(logDir, { recursive: true });
}
const resetMessage = `Log reset at ${new Date().toISOString()}\n\n `;
const logFiles = glob
  .globSync('**/whatsapp*.log', { cwd: logDir, absolute: true })
  .concat(path.join(logDir, 'whatsapp-error.log'), path.join(logDir, 'whatsapp.log'));
logFiles.forEach((file) => writefile(file, resetMessage));

// Set to track active sender names
const activeSenders = new Set();

con.on('messages', async (replier) => {
  const senderName = replier.senderId;
  const text = replier.receivedText?.trim();
  if (replier.isGroup) return; // Ignore group messages

  console.log(
    'Received message:',
    text,
    'from',
    senderName,
    'at',
    new Date().toISOString(),
    'is group:',
    replier.isGroup,
    'is admin:',
    replier.isAdmin
  );

  if (activeSenders.has(senderName)) {
    // console.log(`Skipping message from ${senderName}: Process already running.`);
    replier.reply('Please wait for the previous process to complete.');
    return; // Ignore if a process is already running for this sender
  }

  activeSenders.add(senderName); // Add sender to active list

  try {
    // find ./whatsapp_handlers/*.{js,ts}
    const modules = glob
      .globSync('*.{js,ts}', { cwd: path.join(__dirname, 'whatsapp_handlers'), absolute: true })
      .filter((f) => {
        const found = fs.existsSync(f);
        // console.log('module', f, 'found:', found);
        return found;
      });
    for (const modulePath of modules) {
      try {
        const mod = await loadModule(modulePath);
        if (mod && mod.default) {
          await mod.default(replier); // Ensure the module completes execution
        }
      } catch (e) {
        console.error(`Error loading module ${modulePath}:`, e);
        whatsappLogger.error({ err: e });
      }
    }
  } catch (e) {
    whatsappLogger.error({ err: e });
  } finally {
    activeSenders.delete(senderName); // Remove sender from active list
  }
});

con
  .setup()
  .connect()
  .catch((e) => whatsappLogger.error({ err: e }));
