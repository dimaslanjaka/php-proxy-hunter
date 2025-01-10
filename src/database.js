import { getRelativePath } from './func.js';
import ProxyDB from './ProxyDB.js';

export const db = new ProxyDB(getRelativePath('src/database.sqlite'), true);
export const database = db;
export const proxyDb = db;
