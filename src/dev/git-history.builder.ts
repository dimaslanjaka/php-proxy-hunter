import fs from 'fs';
import path from 'path';
import { gitHistoryToJson } from './git-history-to-json';

function main() {
  // Get last [n] commits by default to avoid buffer issues
  const data = gitHistoryToJson({ last: 1000 });
  const outDir = path.join(process.cwd(), 'public', 'data');
  if (!fs.existsSync(outDir)) {
    fs.mkdirSync(outDir, { recursive: true });
  }
  const outFiles = [
    path.join(outDir, 'git-history.json'),
    path.join(process.cwd(), 'dist/react/data/git-history.json')
  ];
  for (const outFile of outFiles) {
    const relativePath = path.relative(process.cwd(), outFile);
    fs.writeFileSync(outFile, JSON.stringify(data, null, 2), 'utf8');
    console.log(`Git history written to ${relativePath}`);
  }
}

main();
