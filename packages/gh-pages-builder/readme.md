# GitHub Pages Builder

A utility package for building GitHub Pages documentation from markdown files with automatic table of contents generation.

## Features

- 📄 **Markdown Processing** - Processes all `.md` files in the project
- 🔗 **Table of Contents** - Auto-generates TOC with `<!-- toc -->` placeholder
- 📂 **Directory Structure** - Maintains original directory structure
- 🏠 **README to Index** - Converts `readme.md` files to `index.md` for GitHub Pages
- 🎯 **GitHub-compatible Slugs** - Uses GitHub-style heading anchors

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
3. **Outputs** processed files to `tmp/docs/` directory
4. **Displays** a tree structure of the generated documentation

The script is configured as a binary (`gh-pages-builder`) and can be executed directly with `npx gh-pages-builder` once installed.

## Table of Contents Generation

To add a table of contents to any markdown file, simply add this comment:

```markdown
<!-- toc -->
```

The builder will replace it with a markdown list of all headings in the file with proper anchor links.

## Configuration

The builder supports flexible configuration through a `gh-pages-builder.config.cjs` file in your project root. The configuration system automatically detects and handles both synchronous and asynchronous configuration patterns.

### Configuration File Location

Create a file named `gh-pages-builder.config.cjs` in your project root directory.

### Configuration Patterns

#### 1. Synchronous Function Export
```javascript
module.exports = function () {
  return {
    inputPattern: '**/*.md',
    outputDir: 'tmp/docs',
    ignorePatterns: ['**/node_modules/**', '**/dist/**'],
    tocPlaceholder: /<!--\s*toc\s*-->/i,
    renameReadme: true,
    processing: {
      generateToc: true,
      enableAnchors: true,
      tocIndentSize: 2
    }
  };
};
```

#### 2. Asynchronous Function Export
```javascript
module.exports = async function () {
  // You can perform async operations like API calls, file reads, etc.
  const settings = await fetchSettingsFromAPI();

  return {
    inputPattern: '**/*.md',
    outputDir: 'tmp/docs',
    ignorePatterns: settings.ignorePatterns,
    tocPlaceholder: /<!--\s*toc\s*-->/i,
    renameReadme: true,
    processing: {
      generateToc: true,
      enableAnchors: true,
      tocIndentSize: 2
    }
  };
};
```

#### 3. Direct Object Export
```javascript
module.exports = {
  inputPattern: '**/*.md',
  outputDir: 'tmp/docs',
  ignorePatterns: ['**/node_modules/**', '**/dist/**'],
  tocPlaceholder: /<!--\s*toc\s*-->/i,
  renameReadme: true
};
```

### Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `inputPattern` | string | `'**/*.md'` | Glob pattern to find markdown files |
| `outputDir` | string | `'tmp/docs'` | Output directory for processed files |
| `ignorePatterns` | string[] | See defaults | Patterns to ignore when searching |
| `tocPlaceholder` | RegExp | `/<!--\s*toc\s*-->/i` | Pattern to match TOC placeholder |
| `renameReadme` | boolean | `true` | Rename README.md to index.md |
| `processing.generateToc` | boolean | `true` | Generate table of contents |
| `processing.enableAnchors` | boolean | `true` | Enable heading anchors |
| `processing.tocIndentSize` | number | `2` | Spaces per indent level in TOC |

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

## Output

Generated documentation will be available in the `tmp/docs/` directory, ready for deployment to GitHub Pages.
