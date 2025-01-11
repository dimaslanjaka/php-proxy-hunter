import fs from 'fs-extra';
import { array_unique, jsonParseWithCircularRefs, jsonStringifyWithCircularRefs, writefile } from 'sbg-utility';
import path from 'upath';
import xlsx from 'xlsx';

export interface TableCell {
  [key: string]: any;
  NO: number;
  'NAMA OBAT': string;
  PENJELASAN: string;
  images: string[];
  /** total char matched */
  nearest: number;
  /** is highest total char matched */
  isNearest: boolean;
}

export default class xlsxParser {
  /**
   * parse xlsx file to javascript object
   * @param xlsxPath xlsx file path
   * @param cache use cache?
   * @returns
   */
  static read(xlsxPath: string, cache = true) {
    const workbook = xlsx.readFile(xlsxPath);
    const sheet_name_list = workbook.SheetNames;
    let json = xlsx.utils.sheet_to_json(workbook.Sheets[sheet_name_list[0]]) as TableCell[];
    if (cache) {
      const jsonFile = path.join(process.cwd(), 'tmp', path.basename(xlsxPath) + '.json');
      // restore cache
      if (fs.existsSync(jsonFile)) {
        json = Object.assign(json, jsonParseWithCircularRefs(fs.readFileSync(jsonFile, 'utf-8')));
      }
      // check images
      json = json.map((item) => {
        if (Array.isArray(item.images)) {
          item.images = array_unique(item.images);
        }
        return item;
      });
      // rewrite cache
      writefile(jsonFile, jsonStringifyWithCircularRefs(json));
    }
    return json;
  }
}
