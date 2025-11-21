import { FlatCompat } from '@eslint/eslintrc';
import js from '@eslint/js';
import tsParser from '@typescript-eslint/parser';
import globals from 'globals';
import jsonc from 'jsonc-parser';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Initialize FlatCompat to support older ESLint config compatibility
const compat = new FlatCompat({
  baseDirectory: __dirname,
  recommendedConfig: js.configs.recommended,
  allConfig: js.configs.all
});

// Load and parse Prettier configuration from file
const prettierrc = jsonc.parse(fs.readFileSync(path.resolve(__dirname, '.prettierrc.json'), 'utf-8'));

export default [
  {
    // File paths and patterns to be completely ignored by ESLint
    ignores: [
      // Markdown files
      '**/*.md',

      // HTML templates or static HTML content
      '**/*.html',

      // Python files
      '**/*.py',

      // Plain text files
      '**/*.txt',

      // Temporary working directories
      '**/tmp/**',

      // Application-specific non-source directories
      '**/app/**',

      // Build output directories
      '**/dist/**',

      // Dependency directories
      '**/node_modules/**',

      // Code coverage reports
      '**/coverage/**',

      // Log storage directories
      '**/logs/**',

      // Third-party vendor scripts
      '**/vendor/**',

      // Minified files (e.g., *.min.js, *.min.css)
      '**/min.*',

      // Lock files for dependency managers
      '**/*.lock',

      // Public static assets
      '**/public/**',

      // Yarn internal folder
      '**/.yarn/**'
    ]
  },

  // Apply recommended rule sets from ESLint, TypeScript, and Prettier
  ...compat.extends(
    // ESLint core recommended rules
    'eslint:recommended',

    // TypeScript-enhanced ESLint recommended rules
    'plugin:@typescript-eslint/eslint-recommended',

    // Additional TypeScript best-practice rules
    'plugin:@typescript-eslint/recommended',

    // Prettier formatting + conflict resolution
    'plugin:prettier/recommended'
  ),

  {
    linterOptions: {
      // Notify when ESLint disable comments are unnecessary
      reportUnusedDisableDirectives: true
    },

    languageOptions: {
      // Inject various global variables for browser, Node, AMD, Jest, etc.
      globals: {
        ...globals.browser,
        ...globals.amd,
        ...globals.node,
        ...globals.jest,

        // Google reCAPTCHA
        grecaptcha: 'readonly',

        // jQuery globals
        $: 'readonly',
        jQuery: 'readonly',

        // Google Ads global
        adsbygoogle: 'writable',

        // Hexo static site generator global
        hexo: 'readonly'
      },

      // TypeScript parser for ESLint
      parser: tsParser,

      // Enable ECMAScript 2020 syntax
      ecmaVersion: 2020,

      // Allow usage of ES modules
      sourceType: 'module'
    },

    rules: {
      // Enforce Prettier formatting rules with custom overrides
      'prettier/prettier': [
        'error',
        Object.assign(prettierrc, {
          overrides: [
            {
              // Exclude minified files from formatting
              excludeFiles: ['*.min.js', '*.min.cjs', '*.min.css', '*.min.html', '*.min.scss'],

              // File types to apply Prettier formatting
              files: ['*.js', '*.css', '*.sass', '*.html', '*.md', '*.ts'],

              // Always require semicolons
              options: { semi: true }
            },
            {
              // Use HTML parser for templating languages
              files: ['*.ejs', '*.njk', '*.html'],
              options: { parser: 'html' }
            }
          ]
        })
      ],

      // Disable requirement for explicit return type on functions
      '@typescript-eslint/explicit-function-return-type': 'off',

      // Disable the base JS unused-vars rule (use TS version instead)
      'no-unused-vars': 'off',

      // Allow intentionally unused variables and parameters if they start with "_"
      '@typescript-eslint/no-unused-vars': [
        'error',
        {
          argsIgnorePattern: '^_',
          varsIgnorePattern: '^_',
          caughtErrorsIgnorePattern: '^_'
        }
      ],

      // Allow the use of the `any` type
      '@typescript-eslint/no-explicit-any': 'off',

      // Configure restrictions and allowed names for aliasing `this`
      '@typescript-eslint/no-this-alias': [
        'error',
        {
          // Disallow destructuring when aliasing `this`
          allowDestructuring: false,

          // Permit specific safe aliases
          allowedNames: ['self', 'hexo']
        }
      ],

      // Turn off stylistic enforcement on arrow function bodies
      'arrow-body-style': 'off',

      // Disable enforcement of arrow callbacks
      'prefer-arrow-callback': 'off',

      // Allow empty catch blocks (useful for intentional no-op error handling)
      'no-empty': ['error', { allowEmptyCatch: true }]
    }
  },

  {
    // Overrides for plain JavaScript and CommonJS environments
    files: ['**/*.js', '**/*.cjs'],

    rules: {
      // Allow use of require() in CommonJS files
      '@typescript-eslint/no-var-requires': 'off',

      // Allow require-style imports
      '@typescript-eslint/no-require-imports': 'off',

      // Disable restrictions on triple-slash directive references
      '@typescript-eslint/triple-slash-reference': 'off'
    }
  },

  {
    // Overrides for ECMAScript module files
    files: ['**/*.mjs'],

    rules: {
      // Disable triple-slash reference restrictions for .mjs files
      '@typescript-eslint/triple-slash-reference': 'off'
    }
  }
];
