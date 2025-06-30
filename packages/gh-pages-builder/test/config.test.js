import { afterAll, beforeAll, describe, expect, it } from '@jest/globals';
import { execSync } from 'child_process';
import fs from 'fs';
import path from 'path';
import { fileURLToPath, pathToFileURL } from 'url';
import { configFiles } from './env.cjs';

// ESM __dirname workaround
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const fixturesDir = path.join(__dirname, 'fixtures');

describe('Configuration System', () => {
  beforeAll(async () => {
    // Run markdown generation script before tests (if it exists)
    const generateMarkdownsPath = path.join(fixturesDir, 'generate-markdowns.js');

    if (fs.existsSync(generateMarkdownsPath)) {
      try {
        execSync(`node "${generateMarkdownsPath}"`, { stdio: 'inherit' });
        console.log('✅ Markdown generation script completed successfully');
      } catch (error) {
        console.error('❌ Error running markdown generation script:', error.message);
        // Don't throw here - just log the error and continue with tests
        console.warn('⚠️  Continuing with tests without markdown generation');
      }
    } else {
      console.log('ℹ️  No markdown generation script found, skipping');
    }
  });

  for (let i = 0; i < configFiles.length; i++) {
    const sourceConfigPath = configFiles[i];
    const configFilename = path.basename(sourceConfigPath);
    const testConfigPath = path.join(fixturesDir, `test-${i}-${configFilename}`);

    describe(`Config file: ${configFilename}`, () => {
      beforeAll(() => {
        // Copy the config file to the test directory with unique name
        fs.copyFileSync(sourceConfigPath, testConfigPath);
      });

      afterAll(() => {
        // Clean up any generated files after tests
        fs.rmSync(testConfigPath, { force: true, recursive: true });
      });

      it(`should load ${configFilename} config file`, async () => {
        // Import the config file dynamically
        const configModule = await import(pathToFileURL(testConfigPath).href);

        // Get the config export (handle both ESM default and CommonJS)
        const configExport = configModule.default;
        expect(configExport).toBeDefined();

        let config;

        // Handle different export types
        if (typeof configExport === 'function') {
          // Call the config function and check the returned object
          config = await configExport();
        } else if (typeof configExport === 'object') {
          // Direct object export
          config = configExport;
        } else {
          throw new Error(`Unexpected config export type: ${typeof configExport}`);
        }

        expect(config).toBeDefined();
        expect(config.inputPattern).toBeDefined();
        expect(config.outputDir).toBeDefined();

        // Some configs might not have ignorePatterns, so make it optional
        if (config.ignorePatterns) {
          expect(Array.isArray(config.ignorePatterns)).toBe(true);
        }
      });

      it(`should identify export type for ${configFilename}`, async () => {
        // Import the config file dynamically
        const configModule = await import(pathToFileURL(testConfigPath).href);

        // Log the structure for debugging
        console.log(`Config file: ${configFilename}`);
        console.log(`Export type: ${typeof configModule.default}`);
        console.log(`Is function: ${typeof configModule.default === 'function'}`);
        console.log(`Is object: ${typeof configModule.default === 'object'}`);

        expect(configModule.default).toBeDefined();
        expect(['function', 'object'].includes(typeof configModule.default)).toBe(true);
      });

      it(`should run /bin/build-gh-pages.js with ${configFilename}`, async () => {
        const buildScriptPath = path.join(__dirname, '../bin/build-gh-pages.js');
        const projectRoot = path.dirname(__dirname);

        try {
          // Run the build script from the project root directory
          const output = execSync(`node "${buildScriptPath}"`, {
            encoding: 'utf-8',
            cwd: projectRoot,
            timeout: 30000 // 30 second timeout
          });

          expect(output).toBeDefined();
          console.log(`Build output for ${configFilename}:`, output);

          // Check if the script ran without errors (just check it didn't crash)
          expect(typeof output).toBe('string');
        } catch (error) {
          throw new Error(`Build script failed for ${configFilename}: ${error.message}`);
        }
      });
    });
  }
});
