<?php

$cwd = __DIR__ . '/..';

require_once $cwd . '/vendor/autoload.php';

use Symfony\Component\Translation\Translator;
use Symfony\Component\Translation\Loader\YamlFileLoader;

// Initialize Translator
$translator = new Translator('en');
$translator->addLoader('yaml', new YamlFileLoader());

// Load translation files
$translator->addResource('yaml', $cwd . '/translations/messages.en.yaml', 'en');
$translator->addResource('yaml', $cwd . '/translations/messages.id.yaml', 'id');

// Test different locales
$locales = ['en', 'id'];
foreach ($locales as $locale) {
  $translator->setLocale($locale);

  echo "Locale: $locale\n";
  echo "hello: " . $translator->trans('hello') . "\n";
  echo "welcome: " . $translator->trans('welcome') . "\n";
  echo "select_user: " . $translator->trans('select_user') . "\n";
  echo "--------------------------\n";
}
