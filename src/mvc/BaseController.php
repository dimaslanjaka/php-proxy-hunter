<?php

namespace PhpProxyHunter;

/**
 * BaseController class
 *
 * This class serves as a base controller for the MVC framework.
 * It provides common functionality for controllers, such as handling CLI requests,
 * setting output files, and rendering views.
 */
class BaseController
{
  protected $isCLI = false;

  public function __construct()
  {
    // Check if the request is from CLI
    $this->isCLI = php_sapi_name() === 'cli';
  }

  /**
   * Index action method
   *
   * Returns the content of the output file as a JSON array.
   */
  public function indexAction()
  {
    return [];
  }
}
