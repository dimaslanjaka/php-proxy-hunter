<?php

/**
 * PHP-CS-Fixer configuration file
 *
 * Run with:
 *   composer exec php-cs-fixer fix
 */

require_once __DIR__ . '/scripts/php-cs-fixer/autoload.php';

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
    ->ignoreVCS(true);
// Ignore version control folders (.git, etc.)

// ---------------------------------------------
// Base configuration object
// ---------------------------------------------
$config = new Config();

$cliArgs = $_SERVER['argv'] ?? [];

// If the user passed explicit path arguments to the `php-cs-fixer fix` command
// (for example: `php-cs-fixer fix path/to/file.php`), the CLI will override
// the paths supplied by the configuration file and emit the warning:
// "Paths from configuration file have been overridden by paths provided as
// command arguments." To avoid that warning when the user intends to pass
// paths, only set the Finder when no explicit (non-option) path arguments are
// present.
$hasPathArg = false;
foreach ($cliArgs as $i => $arg) {
  // Skip script and command name (indices 0 and 1)
  if ($i < 2) {
    continue;
  }

  // Treat any argument not starting with '-' as a path (options like --diff
  // start with '-').
  if (strpos($arg, '-') !== 0) {
    $hasPathArg = true;
    break;
  }
}

if (!$hasPathArg) {
  $config->setFinder($finder);
}

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
    ->setCacheFile('tmp/locks/.php-cs-fixer.cache');
// Custom cache file path

$rules = [
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
  // Enforce single space around string concatenation operator
  'concat_space' => ['spacing' => 'one'],
];

// ---------------------------------------------
// Custom fixers and conditional rules based on available fixers
// ---------------------------------------------

$customFixers = [];

// Enable custom global_one_line fixer
$customFixers[]                  = new PhpCsFixerCustom\GlobalOneLineFixer();
$rules['Custom/global_one_line'] = true;

// Detect available fixer names (compatible with all PHP-CS-Fixer versions)
$factory = new PhpCsFixer\FixerFactory();
$factory->registerBuiltInFixers();

$availableFixers = array_map(
  fn ($fixer) => $fixer->getName(),
  $factory->getFixers()
);

// Safe helper
function fixer_exists(string $name, array $available): bool {
  return in_array($name, $available, true);
}

// Force only one statement per physical line
// Example: "unset(...); gc_collect_cycles();" → two separate lines
if (fixer_exists('no_multiple_statements_per_line', $availableFixers)) {
  $rules['no_multiple_statements_per_line'] = true;
} else {
  // Fallback: use custom fixer if built-in one is not available
  $customFixers[]                                  = new PhpCsFixerCustom\CustomNoMultipleStatementsPerLineFixer();
  $rules['Custom/no_multiple_statements_per_line'] = true;
}

// Register custom fixers
$config->registerCustomFixers($customFixers);

// ---------------------------------------------
// Coding standard rules
// ---------------------------------------------

return $config->setRules($rules);
