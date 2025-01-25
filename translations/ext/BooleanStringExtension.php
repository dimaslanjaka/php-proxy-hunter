<?php

namespace PhpProxyHunter;

use InvalidArgumentException;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class BooleanStringExtension extends AbstractExtension
{
  const BOOLEAN_STRING_TRUE = 'true';
  const BOOLEAN_STRING_FALSE = 'false';

  /**
   * {@inheritdoc}
   */
  public function getFilters()
  {
    return [
      new TwigFilter('boolean_string', [$this, 'isBoolean']),
      new TwigFilter('boolean_as_string', [$this, 'isBoolean']),
    ];
  }

  /**
   * Returns a 'true' or 'false' string based on the value of a boolean.
   *
   * @param bool $value
   *   The boolean value to test.
   *
   * @return string
   *   A string representation of the boolean value ('true' or 'false').
   *
   * @throws InvalidArgumentException
   *   Thrown if the provided value is not a boolean.
   *
   * Example usage in a Twig template:
   *
   * ```twig
   * {{ true | boolean_string }}   {# Outputs: 'true' #}
   * {{ false | boolean_string }}  {# Outputs: 'false' #}
   * {{ 'not_a_boolean' | boolean_string }}  {# This will throw an InvalidArgumentException #}
   * ```
   */
  public function isBoolean($value)
  {
    if (!is_bool($value)) {
      throw new InvalidArgumentException('The value is not a boolean.');
    }

    return $value ? self::BOOLEAN_STRING_TRUE : self::BOOLEAN_STRING_FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getName()
  {
    return 'boolean_string';
  }
}
