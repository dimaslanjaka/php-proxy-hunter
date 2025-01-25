<?php

namespace PhpProxyHunter;

use BrightNucleus\CountryCodes\Country;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CountryCodeExtension extends AbstractExtension
{
  /**
   * Returns an array of functions that are added to Twig.
   *
   * @return array
   */
  public function getFunctions()
  {
    return [
      new TwigFunction('country_name_from_code', [$this, 'getCountryNameFromCode']),
      new TwigFunction('country_code_from_name', [$this, 'getCountryCodeFromName']),
    ];
  }

  /**
   * Get country name from country code.
   *
   * @param string $code
   * @return string
   *
   * ```twig
   * {{ country_name_from_code('US') }}  {# Outputs: United States #}
   * ```
   */
  public function getCountryNameFromCode($code)
  {
    return Country::getNameFromCode($code);
  }

  /**
   * Get country code from country name.
   *
   * @param string $name
   * @return string
   *
   * ```twig
   * {{ country_code_from_name('United States') }}  {# Outputs: US #}
   * ```
   */
  public function getCountryCodeFromName($name)
  {
    return Country::getCodeFromName($name);
  }
}
