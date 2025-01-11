import path from 'upath';
import getDirname from '../dirname.js';
import xlsxParser from '../xlsxParser.js';

const __resolve = getDirname();
const __filename = __resolve.__filename;
const __dirname = path.dirname(__filename);

const xlData = xlsxParser.read(path.join(__dirname, '../data/xlsxData.xlsx'));

const data = xlData.find((item) => new RegExp('^hio', 'i').test(item['NAMA OBAT']));
console.log(data);
