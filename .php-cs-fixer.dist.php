<?php

// composer exec php-cs-fixer fix

use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

$finder = Finder::create()
  ->in(__DIR__)
  ->exclude(['vendor', 'node_modules', 'phpliteadmin', 'adminer', 'phpmyadmin', 'tmp', 'logs', 'backups'])
  ->name('*.php')
  ->ignoreDotFiles(true)
  ->ignoreVCS(true);

$config = new Config();

// setParallelConfig was introduced in newer php-cs-fixer releases. Guard
// the call so the config remains compatible with older installed versions.
if (class_exists(ParallelConfigFactory::class) && method_exists($config, 'setParallelConfig')) {
  try {
    $config->setParallelConfig(ParallelConfigFactory::detect());
  } catch (\Throwable $e) {
    // keep going without parallel config if detection fails
  }
} else {
  // do nothing, keep config compatible with older php-cs-fixer versions
}

$config->setIndent(str_pad('', 2));
$config->setUsingCache(true);

return $config->setRules([
  '@PSR12'                       => true,
  'array_syntax'                 => ['syntax' => 'short'],
  'no_unused_imports'            => true,
  'single_quote'                 => true,
  'binary_operator_spaces'       => ['default' => 'align_single_space_minimal'],
  'blank_line_after_opening_tag' => true, // ensures newline after <?php
  'trailing_comma_in_multiline'  => ['elements' => ['arrays']],
  'braces'                       => [
    'allow_single_line_closure'                   => true,
    'position_after_functions_and_oop_constructs' => 'same',
  ],
])
  ->setFinder($finder)
  ->setCacheFile('tmp/locks/.php-cs-fixer.cache')
  ->setRiskyAllowed(true);
