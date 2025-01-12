import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { loadModule } from '../Function.js';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

loadModule(path.join(__dirname, 'fixtures/module_to_load.ts')).then((module) => {
  console.log('Module loaded:', module);
});
loadModule(path.join(__dirname, '../whatsapp_handlers/proxy.ts')).then((module) => {
  console.log('Module loaded:', module);
});
