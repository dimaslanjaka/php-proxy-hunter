#!/usr/bin/env node
/**
 * @fileoverview GitHub Pages Build Script
 * @description Processes markdown files, generates table of contents, and prepares documentation for GitHub Pages
 * @author GitHub Copilot
 * @version 1.0.0
 */

import * as glob from 'glob';
import { buildMarkdowns } from './build-markdown.js';
import { loadConfigWithDefaults } from './config.js';
import { projectDir } from './init.js';
import console from './logger.js';

// === Main Processing ===

/**
 * Main build function that processes markdown files according to configuration
 * @returns Promise<{files: string[], outputDir: string}> Object containing generated file paths and output directory
 */
export default async function buildGitHubPages(): Promise<{ files: string[]; outputDir: string }> {
  // Load configuration
  const config = await loadConfigWithDefaults();

  console.log('🔧 Configuration loaded:', config);

  /**
   * Find all markdown files in the project directory
   */
  const markdownFiles = glob.sync(config.inputPattern, {
    posix: true,
    cwd: projectDir,
    ignore: config.ignorePatterns
  });

  console.log(`📁 Found ${markdownFiles.length} markdown files`);

  const result = buildMarkdowns(markdownFiles, config);

  console.log(`✅ Processed ${result.files.length} markdown files into "${result.outputDir}"`);

  return result;
}
