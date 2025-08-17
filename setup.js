import './.env.mjs';
import { buildTailwind } from './tailwind.build.js';
import { copyIndexHtml } from './vite-plugin.js';

buildTailwind();
copyIndexHtml();
