#!/usr/bin/env node
/**
 * @fileoverview GitHub Pages Build Script
 * @description Processes markdown files, generates table of contents, and prepares documentation for GitHub Pages
 * @author GitHub Copilot
 * @version 1.0.0
 */

import fs from 'fs';
import * as glob from 'glob';
import MarkdownIt from 'markdown-it';
import anchor from 'markdown-it-anchor';
import path from 'path';
import { fileURLToPath } from 'url';
import { loadConfigWithDefaults } from './config.js';

// ESM __dirname workaround
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const projectRoot = path.resolve(__dirname, '../../..');

/**
 * Slugify headings like GitHub
 * @param s - The string to slugify
 * @returns A URL-safe slug string
 */
export function slugify(s: string) {
  return encodeURIComponent(
    s
      .trim()
      .toLowerCase()
      .replace(/[^\w\s-]/g, '')
      .replace(/\s+/g, '-')
  );
}

export const md = new MarkdownIt(undefined, undefined).use(anchor, {
  slugify // use custom slugify for consistency
});

/**
 * Generate Markdown Table of Contents from markdown string
 * @param markdown - Raw markdown content to parse
 * @returns Markdown-formatted table of contents with links
 */
export function renderTocFromMarkdown(markdown: string) {
  const headers = [];

  const tokens = md.parse(markdown, {});

  for (let i = 0; i < tokens.length; i++) {
    const token = tokens[i];
    if (token.type === 'heading_open') {
      const level = parseInt(token.tag.slice(1), 10);
      const inlineToken = tokens[i + 1];
      const title = inlineToken.content;
      const slug = slugify(title);
      headers.push({ level, title, slug });
    }
  }

  // Generate markdown list: `- [Title](#slug)`
  const tocMarkdown = headers
    .map((h) => {
      const indent = '  '.repeat(h.level - 1);
      return `${indent}- [${h.title}](#${h.slug})`;
    })
    .join('\n');

  return tocMarkdown;
}

/**
 * Recursively print directory structure in tree format
 * @param dirPath - The directory path to print
 * @param prefix The prefix string for tree formatting
 */
function printDirectory(dirPath: string, prefix = '') {
  const items = fs.readdirSync(dirPath, { withFileTypes: true });

  items.forEach((item, index) => {
    const isLast = index === items.length - 1;
    const pointer = isLast ? '└── ' : '├── ';
    console.log(prefix + pointer + item.name);

    if (item.isDirectory()) {
      const newPrefix = prefix + (isLast ? '    ' : '│   ');
      printDirectory(path.join(dirPath, item.name), newPrefix);
    }
  });
}

// === Main Processing ===

/**
 * Main build function that processes markdown files according to configuration
 */
export default async function buildGitHubPages() {
  // Load configuration
  const config = await loadConfigWithDefaults();

  console.log('🔧 Configuration loaded:', config);

  /**
   * Find all markdown files in the project directory
   */
  const markdownFiles = glob.sync(config.inputPattern, {
    posix: true,
    cwd: projectRoot,
    ignore: config.ignorePatterns
  });

  console.log(`📁 Found ${markdownFiles.length} markdown files`);

  /**
   * Output directory for processed markdown files
   */
  const outputMarkdownDir = path.join(projectRoot, config.outputDir.markdown);

  // Clear output directory if it exists
  if (fs.existsSync(outputMarkdownDir)) {
    fs.rmSync(outputMarkdownDir, { recursive: true, force: true });
    console.log(`🗑️ Cleared existing output directory: ${outputMarkdownDir}`);
  }

  markdownFiles.forEach((filePath) => {
    const fullPath = path.join(projectRoot, filePath);
    let markdown = fs.readFileSync(fullPath, 'utf-8');
    let outputFilePath = path.join(outputMarkdownDir, filePath);

    // Handle README renaming if configured
    if (config.renameReadme) {
      const parsed = path.parse(outputFilePath);
      if (parsed.name.toLowerCase() === 'readme') {
        const isCurrentFileIsIndex = path.parse(filePath).name.toLowerCase() === 'index';
        // Check if the file is named 'README' and not already 'index'
        if (!isCurrentFileIsIndex) {
          // Rename README to index.md
          outputFilePath = path.join(parsed.dir, 'index' + parsed.ext);
          console.log(`🔄 Renamed README to index: ${filePath} -> ${outputFilePath}`);
        }
      }
    }

    // Process TOC if enabled and placeholder found
    if (config.processing.generateToc && config.tocPlaceholder.test(markdown)) {
      const tocHtml = renderTocFromMarkdown(markdown);
      console.log(`📄 Rendered HTML TOC for: ${filePath}\n`);
      console.log(tocHtml);
      console.log('\n---\n');
      markdown = markdown.replace(config.tocPlaceholder, tocHtml);
    }

    fs.mkdirSync(path.dirname(outputFilePath), { recursive: true });
    fs.writeFileSync(outputFilePath, markdown, 'utf-8');
    console.log(`✅ Processed: ${filePath} -> ${outputFilePath}`);
  });

  printDirectory(outputMarkdownDir);
  console.log(`\n🎉 Successfully processed ${markdownFiles.length} files to ${config.outputDir}`);
}
