import heicConvert from 'heic-convert';
import { fs, path, readDirAsync } from 'sbg-utility';
import { promisify } from 'util';
import getDirname from '../src/dirname';

const __resolve = getDirname();
const __filename = __resolve.__filename;
const __dirname = path.dirname(__filename);

async function main() {
  const dataDir = path.join(__dirname, '../data');
  const filePaths = await readDirAsync(dataDir);
  for (let i = 0; i < filePaths.length; i++) {
    const filePath = filePaths[i];
    if (path.extname(filePath).toLowerCase() !== '.heic') continue;
    const inputBuffer = await promisify(fs.readFile)(filePath);
    const outputBuffer = await heicConvert({
      buffer: inputBuffer, // the HEIC file buffer
      format: 'PNG' // output format
      // quality: 1 // the jpeg compression quality, between 0 and 1
    });
    const newFilePath = path.join(process.cwd(), 'images', path.basename(filePath, '.HEIC') + '.png');
    if (!fs.existsSync(path.dirname(newFilePath))) fs.mkdirSync(path.dirname(newFilePath), { recursive: true });
    await promisify(fs.writeFile)(newFilePath, Buffer.from(outputBuffer));
  }
}

main();
