<?php

/**
 * PHP-CS-Fixer configuration file
 *
 * Run with:
 *   composer exec php-cs-fixer fix
 */

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

// ---------------------------------------------
// Finder setup: determines which files PHP-CS-Fixer will process
// ---------------------------------------------
$finder = Finder::create()
    ->in(__DIR__) // Scan the current directory recursively
    ->exclude([
      'vendor',        // Skip Composer dependencies
      'node_modules',  // Skip frontend dependencies
      'phpliteadmin',  // Skip bundled third-party tools
      'adminer',
      'phpmyadmin',
      'tmp',           // Skip temporary directories
      'logs',
      'backups',       // Skip backups
    ])
    ->name('*.php')       // Only process PHP files
    ->ignoreDotFiles(true) // Ignore hidden files like .env
    ->ignoreVCS(true);     // Ignore version control folders (.git, etc.)

// ---------------------------------------------
// Base configuration object
// ---------------------------------------------
$config = new Config();

// ---------------------------------------------
// Optional: enable parallel execution when available (PHP-CS-Fixer >= 3.25)
// ---------------------------------------------
// Guard to keep compatibility with older PHP-CS-Fixer versions.
if (
    class_exists('PhpCsFixer\\Runner\\Parallel\\ParallelConfigFactory') && method_exists($config, 'setParallelConfig')
) {
  try {
    // Automatically detect and apply optimal parallel configuration
    $config->setParallelConfig(
      PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect()
    );
  } catch (\Throwable $e) {
    // Continue gracefully if parallel detection fails
  }
}

// ---------------------------------------------
// Basic configuration settings
// ---------------------------------------------
$config
    ->setIndent(str_repeat(' ', 2))  // Use 2 spaces per indentation level
    ->setUsingCache(true)            // Enable cache to speed up subsequent runs
    ->setRiskyAllowed(true)          // Allow risky fixers (some change code meaning)
    ->setCacheFile('tmp/locks/.php-cs-fixer.cache'); // Custom cache file path

// ---------------------------------------------
// Coding standard rules
// ---------------------------------------------
return $config->setRules([
  '@PSR12' => true, // Apply PSR-12 coding style standard

  // Prefer short array syntax: []
  'array_syntax' => ['syntax' => 'short'],

  // Remove unused "use" statements
  'no_unused_imports' => true,

  // Prefer single quotes for strings where possible
  'single_quote' => true,

  // Align binary operators (e.g., =, =>) for readability
  'binary_operator_spaces' => ['default' => 'align_single_space_minimal'],

  // Ensure a blank line after "<?php" opening tag
  'blank_line_after_opening_tag' => true,

  // Add trailing commas in multiline arrays for cleaner diffs
  'trailing_comma_in_multiline' => ['elements' => ['arrays']],

  // Control brace placement and closure formatting
  'braces' => [
    'allow_single_line_closure'                   => true, // Allow one-line anonymous functions
    'position_after_functions_and_oop_constructs' => 'same', // Keep opening brace on same line
  ],

  // Enforce consistent array indentation
  'array_indentation' => true,

  // Ensure indentation consistency (spaces vs tabs)
  'indentation_type' => true,
])
->setFinder($finder);
