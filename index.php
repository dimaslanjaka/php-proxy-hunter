<?php

require_once __DIR__ . '/func.php';

use Symfony\Component\Translation\Translator;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Twig\Extension\DebugExtension as TwigDebugExtension;
use Twig\Loader\FilesystemLoader as TwigFSLoader;
use Twig\Environment as TwigEnvironment;
use PhpProxyHunter\TranslationExtension;

ini_set('memory_limit', '512M');

// Capture the full request URI
$requestUri = $_SERVER['REQUEST_URI'] ?? '';

// Extract the path part of the URI
$requestPath = parse_url($requestUri, PHP_URL_PATH);

// Check if the request is for a static file
$staticFileExtensions = ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'ico', 'svg', 'woff', 'woff2', 'ttf', 'eot'];
$fileExtension = pathinfo($requestPath, PATHINFO_EXTENSION);

if (in_array($fileExtension, $staticFileExtensions)) {
  $filePath = __DIR__ . $requestPath; // Construct the full file path

  if (file_exists($filePath)) {
    // Serve the file content directly
    header('Content-Type: ' . mime_content_type($filePath));
    read_file($filePath);
    exit; // Stop further execution
  }

  // If file doesn't exist, respond with 404
  http_response_code(404);
  echo "$filePath file not found.";
  exit;
}

// Remove leading and trailing slashes and split into segments
$pathSegments = explode('/', trim($requestPath, '/'));

// Default values for controller and action
$controllerBase = !empty($pathSegments[0]) ? $pathSegments[0] : 'default';
$controllerName = str_replace(' ', '', ucwords(str_replace('-', ' ', $controllerBase))) . 'Controller';
$actionBase = !empty($pathSegments[1]) ? $pathSegments[1] : 'index';
// Convert hyphens to camelCase for method names
$actionBase = str_replace(' ', '', lcfirst(ucwords(str_replace('-', ' ', $actionBase))));
$actionName = $actionBase . 'Action';

// Any remaining segments are treated as parameters
$params = array_slice($pathSegments, 2);

// Autoload controllers
function autoloadController($className)
{
  $GLOBALS['loadedControllerPath'] = __DIR__ . '/controllers/' . $className . '.php';
  if (file_exists($GLOBALS['loadedControllerPath'])) {
    require_once $GLOBALS['loadedControllerPath'];
    // Save the loaded controller path globally
    $GLOBALS['loadedControllerPath'] = realpath($GLOBALS['loadedControllerPath']);
  }
}

spl_autoload_register('autoloadController');

// Initialize Twig
$loader = new TwigFSLoader(__DIR__ . '/views');
$twig = new TwigEnvironment($loader, ['debug' => is_debug()]);
if (is_debug()) {
  $twig->addExtension(new TwigDebugExtension());
}

// Initialize Translator
$translator = new Translator('en');
$translator->addLoader('yaml', new YamlFileLoader());

// Load translation files for different languages
$translator->addResource('yaml', __DIR__ . '/translations/messages.en.yaml', 'en');
$translator->addResource('yaml', __DIR__ . '/translations/messages.id.yaml', 'id');

// Detect locale from URL query parameter or cookie
$locale = isset($_GET['hl']) ? $_GET['hl'] : (isset($_COOKIE['locale']) ? $_COOKIE['locale'] : 'en');

// Validate the locale to ensure it's one of the supported locales
$validLocales = ['en', 'id']; // Add more locales as needed
if (!in_array($locale, $validLocales)) {
  $locale = 'en'; // Default locale
}

// Set the detected locale
$translator->setLocale($locale);
$translator->setFallbackLocales(['en']);

// Get all PHP files in the 'translations/ext' folder
$extensionsPath = __DIR__ . '/translations/ext';
$files = glob($extensionsPath . '/*.php');

foreach ($files as $file) {
  require_once $file;  // Include the file (make sure the extension class is autoloaded)

  // Get the class name (assuming class name matches the file name)
  $className = "\PhpProxyHunter\\" . basename($file, '.php');
  if (!class_exists($className)) {
    $className = basename($file, '.php');
  }

  // If the class exists and implements Twig\Extension\ExtensionInterface
  if (class_exists($className) && is_subclass_of($className, \Twig\Extension\ExtensionInterface::class)) {
    $extension = null;
    if (strpos($className, 'TranslationExtension') !== false && isset($translator)) {
      // Pass the $translator to the TranslationExtension constructor
      $extension = new $className($translator);
    } else {
      // No special arguments needed for other extensions
      $extension = new $className();
    }
    // Add the extension to Twig
    if ($extension) {
      $twig->addExtension($extension);
    }
  }
}

// Dispatcher
try {
  // Extract the base name for the view
  $baseControllerName = !empty($pathSegments[0]) ? $pathSegments[0] : 'default';
  $baseActionName = strtolower(str_replace('Action', '', $actionName));

  // Determine the view to render based on controller and action
  $viewName = $baseControllerName == 'default' ? $baseActionName : "{$baseControllerName}/{$baseActionName}";

  // Check if the view exists in the 'views/' folder
  $rawViewPath = __DIR__ . "/views/{$viewName}.twig";
  $viewPath = realpath($rawViewPath);

  $controllerOutput = '';

  // Load when controller exists
  if (class_exists($controllerName)) {
    $controllerInstance = new $controllerName();

    if (!method_exists($controllerInstance, $actionName)) {
      // If view exists, allow rendering without throwing
      if (!$viewPath) {
        throw new Exception("Action $actionName not found in $controllerName.");
      }
      // else: view exists, so just skip controller output
    } else {
      // Call action and capture return value
      $controllerOutput = call_user_func_array([$controllerInstance, $actionName], $params);
    }
  }

  if (!$viewPath && !class_exists($controllerName)) {
    throw new Exception("Both controller '{$controllerName}' and view '{$viewName}.twig' not found.");
  }

  if (!$viewPath) {
    // If no view is found, use controller output directly
    exit(outputUtf8Content($controllerOutput));
  } else {
    // Render the view
    $render_html = $twig->render("{$viewName}.twig", [
      'date' => date(DATE_RFC3339),
      'debug' => is_debug(),
      'locale' => $locale,
      'controller_output' => $controllerOutput,
      'views_debug' => [
        '$controllerName' => $controllerName,
        '$controllerFilePath' => file_exists($GLOBALS['loadedControllerPath']) ? realpath($GLOBALS['loadedControllerPath']) : $GLOBALS['loadedControllerPath'] . " Not found",
        '$baseControllerName' => $baseControllerName,
        '$baseActionName' => $baseActionName,
        '$actionName' => $actionName,
        '$params' => $params,
        '$viewName' => $viewName,
        '$rawViewPath' => $rawViewPath,
        '$resolvedViewPath' => $viewPath,
        '$requestUri' => $requestUri,
        '$requestPath' => $requestPath,
        '$fileExtension' => $fileExtension,
        '$staticMode' => in_array($fileExtension, $staticFileExtensions),
        '$locale' => $locale,
        '$debug' => is_debug(),
      ]
    ]);
  }
} catch (Exception $e) {
  // Handle errors by rendering a Twig error page
  http_response_code(404);
  $md = new Parsedown();
  $render_html = $twig->render('404.twig', [
    'errorMessage' => $md->text($e->getMessage()),
    'errorFile' => $e->getFile(),
    'errorLine' => $e->getLine(),
    'debug' => is_debug(),
    'trace' => print_r($e->getTrace(), true),
    'locale' => $locale
  ]);
}

/**
 * Adds the "nofollow" attribute to external HTTP(S) links in the provided HTML string,
 * except for whitelisted domains.
 *
 * This function parses the provided HTML, finds all anchor tags (`<a>`), and checks whether
 * the `href` attribute contains an external HTTP(S) URL. If the URL is external and not in the
 * whitelist, the function adds the `rel="nofollow noopener noreferer"` and `target="_blank"`
 * attributes to the anchor tag.
 *
 * @param string $html The input HTML string containing anchor (`<a>`) tags.
 * @param array $whitelist An array of domains that should be excluded from the nofollow processing.
 *
 * @return string The modified HTML string with `nofollow`, `noopener`, `noreferrer`, and `target="_blank"` added to external HTTP(S) links, except for those in the whitelist.
 */
function addNoFollowToExternalLinks($html, $whitelist = [])
{
  // Create a DOMDocument instance
  $dom = new DOMDocument();

  // Suppress warnings due to malformed HTML
  @$dom->loadHTML($html);

  // Find all anchor tags
  $links = $dom->getElementsByTagName('a');

  // Regex to match HTTP(S) URLs
  $pattern = '/^https?:\/\/[^\s\/$.?#].[^\s]*$/';

  foreach ($links as $link) {
    // Get the href attribute
    $url = $link->getAttribute('href');

    // Validate the URL is an HTTP(S) URL using regex
    if (preg_match($pattern, $url)) {
      // Check if the URL is external and not in the whitelist
      $host = parse_url($url, PHP_URL_HOST);
      if (strpos($url, $_SERVER['HTTP_HOST']) === false && !in_array($host, $whitelist)) {
        // Add rel="nofollow noopener noreferer" and target="_blank" if external and not whitelisted
        $link->setAttribute('rel', 'nofollow noopener noreferer');
        $link->setAttribute('target', '_blank');
      }
    }
  }

  // Save and return the modified HTML
  return $dom->saveHTML();
}


echo addNoFollowToExternalLinks($render_html, ['www.webmanajemen.com', 'webmanajemen.com']);
