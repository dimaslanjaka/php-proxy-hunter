import fs from 'fs-extra';
import { globSync } from 'glob';
import path from 'upath';
import { fileURLToPath } from 'url';
import { getWhatsappFile, loadModule, whatsappLogger } from './Function.js';
import waConnect from './waConnect.js';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const con = new waConnect();

// Cleanup logs
globSync('**/whatsapp*.log', { cwd: getWhatsappFile('tmp/logs'), absolute: true }).forEach((file) =>
  fs.rmSync(file, { force: true })
);

const activeSenders = new Set(); // Set to track active sender names

con.on('messages', async (replier) => {
  const senderName = replier.senderId;
  const text = replier.receivedText?.trim();
  console.log('Received message:', text, 'from', senderName);

  if (activeSenders.has(senderName)) {
    // console.log(`Skipping message from ${senderName}: Process already running.`);
    replier.reply('Please wait for the previous process to complete.');
    return; // Ignore if a process is already running for this sender
  }

  activeSenders.add(senderName); // Add sender to active list

  try {
    const modules = ['ts', 'js']
      .map((ext) => [
        path.join(__dirname, 'whatsapp_handlers', 'xl.' + ext),
        path.join(__dirname, 'whatsapp_handlers', 'proxy.' + ext)
      ])
      .flat()
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
