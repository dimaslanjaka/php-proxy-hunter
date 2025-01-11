import Bluebird from 'bluebird';
import levenshtein from 'js-levenshtein';
import { path, readDirAsync } from 'sbg-utility';
import getDirname from '../src/dirname';
import xlsxParser from '../src/xlsxParser';

const __resolve = getDirname();
const __filename = __resolve.__filename;
const __dirname = path.dirname(__filename);

const xlData = xlsxParser.read(path.join(__dirname, '../data/xlsxData.xlsx'));

const text = 'hio';
let highestNearest: number = 0;
let highestMatch: string;

Bluebird.all(xlData)
  .filter((item) => 'NAMA OBAT' in item)
  .map(async (item) => {
    item['NAMA OBAT'] = item['NAMA OBAT'].trim();
    if (!item.images) item.images = [];
    const regex = new RegExp('^' + item['NAMA OBAT'], 'i');
    const dataDir = path.join(__dirname, '../data');
    const filePaths = await readDirAsync(dataDir);
    for (let i = 0; i < filePaths.length; i++) {
      const filePath = filePaths[i];
      const fileName = path.basename(filePath).trim();
      // push image when not in index
      if (regex.test(fileName) && !item.images.includes(filePath)) {
        item.images.push(filePath);
      }
    }
    return item;
  })
  .map((item) => {
    item.nearest = levenshtein(text, item['NAMA OBAT']);
    if (item.nearest > highestNearest) {
      highestNearest = item.nearest;
      highestMatch = item['NAMA OBAT'];
    }
    return item;
  })
  .filter((item) => {
    // get first match
    if (new RegExp('^' + text, 'i').test(item['NAMA OBAT'])) {
      return true;
    }
    // get nearest match
    if (new RegExp('^' + highestMatch, 'i').test(item['NAMA OBAT'])) {
      return true;
    }
    return false;
  })
  .map((item) => {
    if (item['NAMA OBAT'] === highestMatch) {
      item.isNearest = true;
    } else {
      item.isNearest = false;
    }
    return item;
  });
