import { existsSync, mkdirSync, writeFileSync } from 'fs';
import { join } from 'path';
import { gitHistoryToJson } from './git-history-to-json';

function main() {
  const data = gitHistoryToJson();
  const outDir = join(process.cwd(), 'public', 'data');
  if (!existsSync(outDir)) {
    mkdirSync(outDir, { recursive: true });
  }
  const outFile = join(outDir, 'git-history.json');
  writeFileSync(outFile, JSON.stringify(data, null, 2), 'utf8');
  console.log(`Git history written to ${outFile}`);
}

main();
