import { dirname } from 'path';
import path from 'upath';
import { fileURLToPath } from 'url';
import { PROJECT_DIR } from '../.env.mjs';
import { loadModule } from './Function.js';
import waConnect from './waConnect.js';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);
const con = new waConnect();

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
        path.join(PROJECT_DIR, 'whatsapp_handlers', 'xl.' + ext),
        path.join(PROJECT_DIR, 'whatsapp_handlers', 'proxy.' + ext)
      ])
      .flat();

    for (const modulePath of modules) {
      try {
        const mod = await loadModule(modulePath);
        if (mod && mod.default) {
          await mod.default(replier); // Ensure the module completes execution
        }
      } catch (moduleError) {
        console.error(`Error loading module ${modulePath}:`, moduleError);
      }
    }
  } finally {
    activeSenders.delete(senderName); // Remove sender from active list
  }
});

con.setup().connect().catch(console.error);
