<?php

namespace PhpProxyHunter;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class TranslationExtension extends AbstractExtension
{
  private $translator;

  public function __construct($translator)
  {
    $this->translator = $translator;
  }

  public function getFilters()
  {
    return [
      new TwigFilter('trans', [$this, 'translate']),
    ];
  }

  public function translate($message)
  {
    error_log("Translating message: $message");
    return $this->translator->trans($message);
  }
}
