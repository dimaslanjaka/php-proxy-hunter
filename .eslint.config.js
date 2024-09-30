// noinspection JSUnusedGlobalSymbols

import typescriptEslintPlugin from "@typescript-eslint/eslint-plugin"; // Import the entire TypeScript ESLint plugin
import prettierPlugin from "eslint-plugin-prettier"; // Import the entire Prettier plugin
import { readFileSync } from "fs"; // Import readFileSync to read files
import globals from "globals";

// Read the Prettier configuration from the JSON file
const prettierConfig = JSON.parse(readFileSync(new URL("./.prettierrc.json", import.meta.url), "utf-8"));

/**
 * @type {import("eslint").ESLint.Options}
 */
const config = [
  {
    // Specifies your current project has own eslint rules without extending parent folder eslint rules
    ignores: ["*.md", "**/tmp/**", "*.html", "*.py", "*.txt", "**/app/**", "**/dist/**", "!.*.{js,cjs,mjs}"], // .eslintignore replacement
    linterOptions: {
      reportUnusedDisableDirectives: true
    },
    languageOptions: {
      ecmaVersion: 2020, // Allows for the parsing of modern ECMAScript features
      sourceType: "module", // Allows for the use of imports
      parser: "@typescript-eslint/parser", // Specifies the ESLint parser
      globals: {
        ...globals.browser,
        ...globals.amd,
        ...globals.node,
        $: "readonly", // jQuery is assigned to $
        jQuery: "readonly", // jQuery is also available as jQuery
        adsbygoogle: "writable"
      }
    },
    rules: {
      // Specify your desired rules for ESLint
      "prettier/prettier": ["error", prettierConfig],
      "@typescript-eslint/explicit-function-return-type": "off", // Disable function without return type
      "no-unused-vars": "off", // Disable original ESLint unused-vars
      "@typescript-eslint/no-unused-vars": [
        "error",
        {
          argsIgnorePattern: "^_",
          varsIgnorePattern: "^_",
          caughtErrorsIgnorePattern: "^_"
        }
      ], // Enable TypeScript ESLint unused-vars and allow unused vars starting with underscore (_)
      "@typescript-eslint/no-explicit-any": "off", // Allow any types
      "@typescript-eslint/no-this-alias": [
        // Rules for this binding
        "error",
        {
          allowDestructuring: false, // Disallow `const { props, state } = this`; true by default
          allowedNames: ["self"] // Allow `const self = this`; `[]` by default
        }
      ],
      // "arrow-body-style" and "prefer-arrow-callback" can cause issues with Prettier, so turn them off.
      "arrow-body-style": "off",
      "prefer-arrow-callback": "off"
    }
  },
  {
    files: ["*.js", "*.cjs"],
    rules: {
      "@typescript-eslint/no-var-requires": "off", // Disable require warning on JS files
      "@typescript-eslint/no-require-imports": "off" // Disable require warning on JS files
    }
  },
  {
    // Enable the recommended configurations
    plugins: {
      "@typescript-eslint": typescriptEslintPlugin, // Use the TypeScript ESLint plugin
      prettier: prettierPlugin // Use the Prettier plugin
    },
    rules: {
      ...typescriptEslintPlugin.configs["eslint-recommended"].rules,
      ...typescriptEslintPlugin.configs["recommended"].rules,
      ...prettierPlugin.configs.recommended.rules
    },
    languageOptions: {
      globals: {
        browser: true,
        amd: true,
        node: true
      }
    }
  }
];

export default config;
