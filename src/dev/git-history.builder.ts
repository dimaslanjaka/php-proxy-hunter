import fs from 'fs';
import path from 'path';
import { gitHistoryToJson } from './git-history-to-json';

function main() {
  const data = gitHistoryToJson();
  const outDir = path.join(process.cwd(), 'public', 'data');
  if (!fs.existsSync(outDir)) {
    fs.mkdirSync(outDir, { recursive: true });
  }
  const outFiles = [path.join(outDir, 'git-history.json'), path.join(process.cwd(), 'dist/react', 'git-history.json')];
  for (const outFile of outFiles) {
    fs.writeFileSync(outFile, JSON.stringify(data, null, 2), 'utf8');
    console.log(`Git history written to ${outFile}`);
  }
}

main();
