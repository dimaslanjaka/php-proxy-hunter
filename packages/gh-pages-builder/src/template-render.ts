import fs from 'fs';
import nunjucks from 'nunjucks';
import path from 'path';
import { fileURLToPath } from 'url';
import { md } from './build-markdown.js';

// __dirname replacement for ESM
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Configure Nunjucks
nunjucks.configure(process.cwd(), { autoescape: true });

// Load markdown
const mdContent = fs.readFileSync(path.join(__dirname, 'example.md'), 'utf-8');
const htmlContent = md.render(mdContent, {});

// Render with Nunjucks
const result = nunjucks.render('template.njk', {
  title: 'Markdown + Nunjucks',
  content: htmlContent
});

console.log(result);
