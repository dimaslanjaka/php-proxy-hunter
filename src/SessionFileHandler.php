<?php

namespace PhpProxyHunter;

use Exception;
use SessionHandlerInterface;

class SessionFileHandler implements SessionHandlerInterface
{
  protected $sess_path;
  protected $prefix;
  protected $postfix;

  /**
   * FileSessionHandler constructor.
   *
   * @param string $sess_path The path to the directory where session files will be stored.
   * @param string $prefix (Optional) The prefix to prepend to session file names. Default is 'sess_'.
   * @param string $postfix (Optional) The postfix to append to session file names.
   *
   * @throws Exception
   */
  public function __construct(string $sess_path, string $prefix = 'sess_', string $postfix = '')
  {
    if (!is_dir($sess_path)) {
      throw new Exception("Cannot use FileSessionHandler, directory '{$sess_path}' not found", 1);
    }

    if (!is_writable($sess_path)) {
      throw new Exception("Cannot use FileSessionHandler, directory '{$sess_path}' is not writable", 2);
    }

    $this->sess_path = $sess_path;
    $this->prefix = $prefix;
    $this->postfix = $postfix;
  }

  /**
   * Initialize session.
   *
   * @param string $path
   * @param string $name
   * @return bool
   */
  public function open($path, $name): bool
  {
    return true;
  }

  /**
   * Close the session.
   *
   * @return bool
   */
  public function close(): bool
  {
    return true;
  }

  /**
   * Create file path for session.
   *
   * @param string $id
   * @return string
   */
  private function createFilePath(string $id): string
  {
    // Sanitize session ID to prevent directory traversal attacks
    $id = preg_replace('/[^a-zA-Z0-9_]/', '', $id);
    $id = str_replace($this->prefix, '', $id);
    return "{$this->sess_path}/{$this->prefix}{$id}{$this->postfix}";
  }

  /**
   * Read session data.
   *
   * @param string $id
   * @return string
   */
  public function read($id): string
  {
    $file = $this->createFilePath($id);
    if (file_exists($file)) {
      return (string)file_get_contents($file);
    }
    return '';
  }

  /**
   * Write session data.
   *
   * @param string $id
   * @param string $data
   * @return bool
   */
  public function write($id, $data): bool
  {
    $file = $this->createFilePath($id);
    if (file_put_contents($file, $data) !== false) {
      return true;
    }
    return false;
  }

  /**
   * Destroy a session.
   *
   * @param string $id
   * @return bool
   */
  public function destroy($id): bool
  {
    $file = $this->createFilePath($id);
    if (file_exists($file) && !unlink($file)) {
      return false;
    }
    return true;
  }

  /**
   * Perform session garbage collection.
   *
   * @param int $max_lifetime
   * @return bool
   */
  public function gc($max_lifetime): bool
  {
    $globs = array_merge(glob("{$this->sess_path}/sess_*"),
        glob("{$this->sess_path}/{$this->prefix}*"),
        glob("{$this->sess_path}/{$this->prefix}*{$this->postfix}"));
    $now = time();
    $three_days_ago = $now - (3 * 24 * 60 * 60); // 3 days in seconds

    foreach ($globs as $file) {
      if (file_exists($file) && (filectime($file) < $three_days_ago)) {
        unlink($file);
      }
    }

    return true;
  }
}
