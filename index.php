<?php

require_once __DIR__ . '/func.php';

// Capture the full request URI
$requestUri = $_SERVER['REQUEST_URI'];

// Extract the path part of the URI
$requestPath = parse_url($requestUri, PHP_URL_PATH);

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
$loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/views');
$twig = new \Twig\Environment($loader);

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
    'debug' => is_debug()
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
    'trace' => print_r($e->getTrace(), true)
  ]);
}
