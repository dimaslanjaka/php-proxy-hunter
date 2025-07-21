<?php

if (!function_exists('tmp')) {
  require_once __DIR__ . '/autoload.php';
  require_once __DIR__ . '/../func-proxy.php';
}

use PhpProxyHunter\BaseController;
use PhpProxyHunter\UserDB;

class ProfileController extends BaseController
{
  public function __construct()
  {
    parent::__construct();
  }

  public function indexAction()
  {
    return [];
  }

  public function editAction()
  {
    $postData = $this->parsePostData(true);
    // if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($postData)) {
    //   return ['message' => 'X Profile updated successfully.', 'success' => true];
    // }
    $email = $_SESSION['authenticated_email'] ?? '';
    if (!empty($email)) {
      if (!empty($postData['username'])) {
        $user = new UserDB();
        $data = $user->select($email);
        if (empty($data)) {
          return [
            'success' => false,
            'message' => "User with email {$email} not found."
          ];
        }
        $newUsername = $postData['username'];
        if (preg_match('/[^a-zA-Z0-9_]|[\s]/', $newUsername)) {
          return [
            'success' => false,
            'message' => "Invalid username: contains special characters or whitespace."
          ];
        }

        $user->update($email, [
          'username' => $newUsername
        ]);
        return [
          'success' => true,
          'message' => "Profile updated successfully."
        ];
      }
    } else {
      return [
        'success' => false,
        'message' => "You must be logged in to edit your profile."
      ];
    }
  }
}
