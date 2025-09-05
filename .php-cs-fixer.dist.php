<?php

// composer exec php-cs-fixer fix

use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

$finder = Finder::create()
    ->in(__DIR__)
    ->exclude(['vendor', 'node_modules']);

$config = new Config();
$config->setParallelConfig(ParallelConfigFactory::detect());
$config->setIndent(str_pad('', 2));

return $config->setRules([
    '@PSR2' => true,
    'array_syntax' => ['syntax' => 'short'],
    'no_unused_imports' => true,
    'braces' => [
        'allow_single_line_closure' => true,
        'position_after_functions_and_oop_constructs' => 'same',
    ],
])
    ->setFinder($finder)
    ->setCacheFile('tmp/.php-cs-fixer.cache');
