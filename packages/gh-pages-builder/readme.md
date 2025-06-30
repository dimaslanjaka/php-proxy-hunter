# GitHub Pages Builder

A powerful documentation builder for GitHub Pages with automatic table of contents generation, template rendering, and markdown processing capabilities. Built with TypeScript for reliability and type safety.

## Features

- 📄 **Markdown Processing** - Processes all `.md` files in the project
- 🔗 **Table of Contents** - Auto-generates TOC with `<!-- toc -->` placeholder
- 📂 **Directory Structure** - Maintains original directory structure
- 🏠 **README to Index** - Converts `readme.md` files to `index.md` for GitHub Pages
- 🎯 **GitHub-compatible Slugs** - Uses GitHub-style heading anchors
- 🎨 **Template Engine Support** - Built-in Nunjucks templating with multi-engine support
- 📦 **TypeScript Support** - Full TypeScript implementation with declaration files
- 🔧 **Cross-platform Git Diff** - Universal git diff tooling for all platforms

## Usage

### Install Dependencies

```bash
npm install
```

### Build Documentation

```bash
npm run build
```

Or run directly:

```bash
npx gh-pages-builder
```

### Development Mode

```bash
npm run dev
```

### Clean Build Directory

```bash
npm run clean
```

## How it Works

1. **Scans** for all `.md` files in the project (excluding common build directories)
2. **Processes** each markdown file:
   - Generates table of contents if `<!-- toc -->` is found
   - Replaces the placeholder with markdown TOC links
   - Converts `readme.md` to `index.md` for GitHub Pages compatibility
   - Applies template rendering using configurable engines
3. **Outputs** processed files to separate `markdown` and `html` directories
4. **Displays** a tree structure of the generated documentation

The script is configured as a binary (`gh-pages-builder`) and can be executed directly with `npx gh-pages-builder` once installed.

## Table of Contents Generation

To add a table of contents to any markdown file, simply add this comment:

```markdown
<!-- toc -->
```

The builder will replace it with a markdown list of all headings in the file with proper anchor links.

## Configuration

The builder supports flexible configuration through configuration files in your project root. The configuration system automatically detects and handles both synchronous and asynchronous configuration patterns.

### Configuration File Formats

The builder supports multiple configuration file formats:
- `gh-pages-builder.config.cjs` (CommonJS)
- `gh-pages-builder.config.mjs` (ESM)
- `gh-pages-builder.config.js` (Auto-detected based on package.json type)

### Configuration Patterns

#### Basic

```javascript
module.exports = {
  inputPattern: '**/*.md',
  outputDir: {
    markdown: 'tmp/markdown',
    html: 'tmp/html'
  },
  ignorePatterns: ['**/node_modules/**', '**/dist/**'],
  tocPlaceholder: /<!--\s*toc\s*-->/i,
  renameReadme: true,
  processing: {
    generateToc: true,
    enableAnchors: true,
    tocIndentSize: 2
  },
  theme: {
    name: 'default',
    engine: 'nunjucks'
  }
};
```

#### Advanced

Synchronous Function Export

```javascript
module.exports = function () {
  return {
    /** same as above object **/
  };
};
```

Asynchronous Function Export

```javascript
module.exports = async function () {
  // You can perform async operations like API calls, file reads, etc.
  const settings = await fetchSettingsFromAPI();

  return {
    /** same as above object **/
  };
};
```

### Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `inputPattern` | string | `'**/*.md'` | Glob pattern to find markdown files |
| `outputDir.markdown` | string | `'tmp/markdown'` | Output directory for processed markdown files |
| `outputDir.html` | string | `'tmp/html'` | Output directory for rendered HTML files |
| `ignorePatterns` | string[] | See defaults | Patterns to ignore when searching |
| `tocPlaceholder` | RegExp | `/<!--\s*toc\s*-->/i` | Pattern to match TOC placeholder |
| `renameReadme` | boolean | `true` | Rename README.md to index.md |
| `processing.generateToc` | boolean | `true` | Generate table of contents |
| `processing.enableAnchors` | boolean | `true` | Enable heading anchors |
| `processing.tocIndentSize` | number | `2` | Spaces per indent level in TOC |
| `theme.name` | string | `'default'` | Theme name for template rendering |
| `theme.engine` | string | `'nunjucks'` | Template engine ('nunjucks', 'handlebars', 'mustache', 'ejs') |

### Default Ignore Patterns
- `**/node_modules/**`
- `**/dist/**`
- `**/build/**`
- `**/.git/**`
- `**/coverage/**`
- `**/docs/**`
- `**/test/**`, `**/tests/**`
- `**/vendor/**`
- `**/composer/**`
- `**/simplehtmldom/**`

### Auto-Detection Features

The configuration loader automatically:
- Detects if your config function is async and handles promises
- Falls back to defaults if no config file is found
- Provides helpful error messages for configuration issues
- Supports hot reloading during development (clears require cache)
- Uses ESM imports with CommonJS config files (`.cjs`) for maximum compatibility

## Dependencies

- **markdown-it** - Markdown parser and renderer
- **markdown-it-anchor** - Generates heading anchors
- **glob** - File pattern matching
- **nunjucks** - Template rendering engine

## TypeScript Support

This package is built with TypeScript and provides:
- Full TypeScript implementation
- Declaration files (`.d.ts`) for type safety
- ESM and CommonJS builds
- Cross-platform compatibility

## Build Output

The builder generates files in separate directories:
- **Markdown**: `tmp/markdown/` - Processed markdown files with TOC
- **HTML**: `tmp/html/` - Rendered HTML files using templates

Both directories maintain the original project structure and are ready for deployment to GitHub Pages.

## Development

### Build Process
```bash
yarn build          # Build both ESM and CJS versions
yarn build:dev      # Build in watch mode
yarn test           # Run tests with build validation
yarn clean          # Clean dist directory
```

### Cross-platform Git Diff
The project includes cross-platform git diff tooling:
```bash
bin/git-diff --help              # Show usage
bin/git-diff FILE               # Show staged diff of file
bin/git-diff --staged-only      # Show all staged changes
```
