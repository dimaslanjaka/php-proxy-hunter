import fs from 'fs';
import { defaults } from 'jest-config';
import path from 'path';
import type { JestConfigWithTsJest } from 'ts-jest';

/**
 * @see {@link https://jestjs.io/docs/configuration}
 * * how to run single test {@link https://stackoverflow.com/questions/28725955/how-do-i-test-a-single-file-using-jest}
 */
const config: JestConfigWithTsJest = {
  preset: 'ts-jest',
  testEnvironment: 'node',
  moduleFileExtensions: [...defaults.moduleFileExtensions, 'mts', 'cts', 'mjs', 'cjs', 'jsx', 'tsx'],
  verbose: true,
  cache: true,
  cacheDirectory: path.join(__dirname, 'tmp/jest'),
  collectCoverageFrom: [
    'src/*.{js,jsx,ts,tsx,mjs,cjs,mts,cts}',
    '!**/node_modules/**',
    '!**/vendor/**',
    '!**/test*/**',
    '!**/*.test.{js,jsx,ts,tsx,mjs,cjs,mts,cts}',
    '!**/*.builder.ts',
    '!**/.deploy_git/**'
  ],
  roots: [`<rootDir>/tests`],
  coveragePathIgnorePatterns: ['/node_modules/', '/dist/', '/tmp/', '/test*/'],
  testMatch: [
    '**/__tests__/**/*.+(ts|tsx|js|jsx|mjs|cjs|mts|cts)',
    '**/?(*.)+(spec|test).+(ts|tsx|js|jsx|mjs|cjs|mts|cts)',
    '**/test/*.test.{ts,tsx,js,jsx,mjs,cjs,mts,cts}',
    '!**/.deploy_git/**'
  ],
  moduleNameMapper: {
    '^(\\.{1,2}/.*)\\.js$': '$1'
  },
  transform: {
    '^.+\\.(ts|tsx|mts|cts)$': [
      'ts-jest',
      {
        babelConfig: {
          presets: [
            [
              '@babel/preset-env',
              {
                targets: { node: 'current' }
              }
            ],
            '@babel/preset-typescript'
          ]
        },
        useESM: true,
        tsconfig: path.join(__dirname, 'tsconfig.jest.json')
      }
    ],
    // Only transform js, jsx, cjs (not mjs)
    '^.+\\.(js|jsx|cjs)$': [
      'babel-jest',
      {
        presets: [['@babel/preset-env', { targets: { node: 'current' } }]]
      }
    ]
  },
  transformIgnorePatterns: ['/node_modules/'],
  // detectLeaks: true,
  // detectOpenHandles: true,
  clearMocks: true,
  collectCoverage: true,
  coverageDirectory: 'coverage/jest',
  coverageProvider: 'v8'
};

// Ensure the 'tmp' directory exists before using it for Jest cache
const tmpDir = path.join(__dirname, 'tmp');
if (!fs.existsSync(tmpDir)) {
  fs.mkdirSync(tmpDir, { recursive: true });
}
if (!fs.existsSync(<string>config.cacheDirectory)) {
  fs.mkdirSync(<string>config.cacheDirectory, { recursive: true });
}

export default config;
