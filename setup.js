import './.env.mjs';
import { buildTailwind } from './tailwind.build.js';
import { copyIndexHtml } from './vite-plugin.js';
import { spawnSync } from 'child_process';

buildTailwind();
copyIndexHtml();
spawnSync('npx', ['--yes', 'update-browserslist-db@latest'], { stdio: 'inherit', shell: true });
