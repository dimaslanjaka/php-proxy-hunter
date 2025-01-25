<?php

require_once __DIR__ . '/func.php';

use Symfony\Component\Translation\Translator;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Twig\Loader\FilesystemLoader as TwigFSLoader;
use Twig\Environment as TwigEnvironment;
use PhpProxyHunter\TranslationExtension;

ini_set('memory_limit', '512M');

// Capture the full request URI
$requestUri = $_SERVER['REQUEST_URI'];

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
$controllerName = !empty($pathSegments[0]) ? ucfirst($pathSegments[0]) . 'Controller' : 'DefaultController';
$actionName = !empty($pathSegments[1]) ? $pathSegments[1] . 'Action' : 'indexAction';

// Any remaining segments are treated as parameters
$params = array_slice($pathSegments, 2);

// Autoload controllers
function autoloadController($className)
{
  $filePath = __DIR__ . '/controllers/' . $className . '.php';
  if (file_exists($filePath)) {
    require_once $filePath;
  }
}

spl_autoload_register('autoloadController');

// Initialize Twig
$loader = new TwigFSLoader(__DIR__ . '/views');
$twig = new TwigEnvironment($loader);

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
error_log("Locale set to: $locale");

// Pass the translator
$twig->addExtension(new TranslationExtension($translator));

// Dispatcher
try {
  // Load when controller exists
  if (class_exists($controllerName)) {
    // Instantiate the controller
    $controllerInstance = new $controllerName();

    // Check if the action method exists
    if (!method_exists($controllerInstance, $actionName)) {
      throw new Exception("Action $actionName not found in $controllerName.");
    }

    // Call the action with parameters
    call_user_func_array([$controllerInstance, $actionName], $params);
  }

  // Extract the base name for the view
  $baseControllerName = strtolower(str_replace('Controller', '', $controllerName));
  $baseActionName = strtolower(str_replace('Action', '', $actionName));

  // Determine the view to render based on controller and action
  $viewName = $baseControllerName == 'default' ? $baseActionName : "{$baseControllerName}/{$baseActionName}";

  // Check if the view exists in the 'views/' folder
  $rawViewPath = __DIR__ . "/views/{$viewName}.twig";
  $viewPath = realpath($rawViewPath);

  if (!$viewPath) {
    if (is_debug()) {
      throw new Exception("View file for **$viewName** not found. (**$rawViewPath**)");
    }
    throw new Exception("View file for **$viewName** not found.");
  }

  // Render the view
  echo $twig->render("{$viewName}.twig", [
    'date' => date(DATE_RFC3339),
    'debug' => is_debug(),
    'locale' => $locale
  ]);
} catch (Exception $e) {
  // Handle errors by rendering a Twig error page
  http_response_code(404);
  $md = new Parsedown();
  echo $twig->render('404.twig', [
    'errorMessage' => $md->text($e->getMessage()),
    'errorFile' => $e->getFile(),
    'errorLine' => $e->getLine(),
    'debug' => is_debug(),
    'trace' => print_r($e->getTrace(), true),
    'locale' => $locale
  ]);
}
