<?php

namespace PhpProxyHunter;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class TranslationExtension extends AbstractExtension
{
  private $translator;

  /**
   * Constructor.
   *
   * @param mixed $translator
   *   The translator service (e.g., Symfony Translator service).
   */
  public function __construct($translator)
  {
    $this->translator = $translator;
  }

  /**
   * {@inheritdoc}
   */
  public function getFilters()
  {
    return [
      new TwigFilter('trans', [$this, 'translate']),
    ];
  }

  /**
   * Translates a message.
   *
   * @param string $message
   *   The message to translate.
   *
   * @return string
   *   The translated message.
   *
   * Example usage in Twig template:
   *
   * ```twig
   * {{ 'hello' | trans }}  {# Translates the 'hello' message #}
   * ```
   */
  public function translate($message)
  {
    return $this->translator->trans($message);
  }
}
