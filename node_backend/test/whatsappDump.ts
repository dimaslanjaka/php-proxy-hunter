import fs from 'fs-extra';
import { whatsappDump } from '../Function.js';

const dump = whatsappDump('Test dump');
console.log(fs.readFileSync(dump, 'utf-8'));
