<?php

class UserDB
{
  private $pdo;

  public function __construct()
  {
    $dbPath = dirname(__DIR__) . '/databases/user.sqlite';
    if (!is_dir(dirname($dbPath))) {
      mkdir(dirname($dbPath), 0777, true);
    }
    $this->pdo = new PDO('sqlite:' . $dbPath);
    $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $this->initTable();
  }

  private function initTable()
  {
    $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                email TEXT UNIQUE NOT NULL,
                password TEXT NOT NULL,
                role TEXT NOT NULL
            )
        ");
    // Add email column if it doesn't exist (for migrations)
    $columns = $this->pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_ASSOC);
    $hasEmail = false;
    foreach ($columns as $col) {
      if ($col['name'] === 'email') {
        $hasEmail = true;
        break;
      }
    }
    if (!$hasEmail) {
      $this->pdo->exec("ALTER TABLE users ADD COLUMN email TEXT UNIQUE");
    }
  }

  // Create user
  public function createUser($username, $email, $password, $role)
  {
    $stmt = $this->pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
    return $stmt->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
  }

  // Read user by id
  public function getUserById($id)
  {
    $stmt = $this->pdo->prepare("SELECT id, username, email, role FROM users WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
  }

  // Read user by username
  public function getUserByUsername($username)
  {
    $stmt = $this->pdo->prepare("SELECT id, username, email, role, password FROM users WHERE username = ?");
    $stmt->execute([$username]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
  }

  // Read user by email
  public function getUserByEmail($email)
  {
    $stmt = $this->pdo->prepare("SELECT id, username, email, role, password FROM users WHERE email = ?");
    $stmt->execute([$email]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
  }

  // Update user
  public function updateUser($id, $username, $email, $role, $password = null)
  {
    if ($password) {
      $stmt = $this->pdo->prepare("UPDATE users SET username = ?, email = ?, role = ?, password = ? WHERE id = ?");
      return $stmt->execute([$username, $email, $role, password_hash($password, PASSWORD_DEFAULT), $id]);
    } else {
      $stmt = $this->pdo->prepare("UPDATE users SET username = ?, email = ?, role = ? WHERE id = ?");
      return $stmt->execute([$username, $email, $role, $id]);
    }
  }

  // Delete user
  public function deleteUser($id)
  {
    $stmt = $this->pdo->prepare("DELETE FROM users WHERE id = ?");
    return $stmt->execute([$id]);
  }

  // List all users
  public function getAllUsers()
  {
    $stmt = $this->pdo->query("SELECT id, username, email, role FROM users");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
}
