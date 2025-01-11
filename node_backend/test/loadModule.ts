import path from 'path';
import getDirname from '../dirname.js';
import { loadModule } from '../Function.js';

const { __dirname } = getDirname();
loadModule(path.join(__dirname, 'fixtures/module_to_load.ts')).then((module) => {
  console.log('Module loaded:', module);
});
