import fs from 'fs';
import MarkdownIt from 'markdown-it';
import anchor from 'markdown-it-anchor';
import path from 'path';
import { writefile } from 'sbg-utility';
import { Config } from './config.js';
import { projectDir } from './init.js';
import console from './logger.js';
import { printDirectory } from './utils.js';

export function buildMarkdowns(markdownFiles: string[], config: Config): { files: string[]; outputDir: string } {
  /**
   * Output directory for processed markdown files
   */
  const outputMarkdownDir = path.join(projectDir, config.outputDir.markdown);
  const generatedFiles: string[] = [];

  markdownFiles.forEach((sourceFilePath) => {
    const fullPath = path.join(projectDir, sourceFilePath);
    let markdown = fs.readFileSync(fullPath, 'utf-8');
    let outputFilePath = path.join(outputMarkdownDir, sourceFilePath);
    const sourceFileDir = path.dirname(sourceFilePath);
    const filesInSourceDir = fs.readdirSync(path.join(projectDir, sourceFileDir));
    const sourceDirHasIndex = filesInSourceDir.some(
      (f) => f.toLowerCase() === 'index.md' || f.toLowerCase() === 'index.html'
    );

    // Handle README renaming if configured
    if (config.renameReadme) {
      const parsed = path.parse(outputFilePath);
      if (parsed.name.toLowerCase() === 'readme') {
        // Rename readme to index if no index file exists
        if (!sourceDirHasIndex) {
          outputFilePath = path.join(parsed.dir, 'index' + parsed.ext);
          console.log(`🔄 Renamed README to index: ${sourceFilePath} -> ${outputFilePath}`);
        }
      }
    }

    // Process TOC if enabled and placeholder found
    if (config.processing.generateToc && config.tocPlaceholder.test(markdown)) {
      const tocHtml = renderTocFromMarkdown(markdown);
      console.log(`📄 Rendered HTML TOC for: ${sourceFilePath}\n`);
      console.log(tocHtml);
      console.log('\n---\n');
      markdown = markdown.replace(config.tocPlaceholder, tocHtml);
    }

    writefile(outputFilePath, markdown);
    generatedFiles.push(outputFilePath);
    console.log(`✅ Processed: ${sourceFilePath} -> ${outputFilePath}`);
  });

  printDirectory(outputMarkdownDir);
  console.log(
    `\n🎉 Successfully processed ${markdownFiles.length} files:\n ${JSON.stringify(config.outputDir, null, 2)}`
  );

  return {
    files: generatedFiles,
    outputDir: outputMarkdownDir
  };
}

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
