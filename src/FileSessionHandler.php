<?php

namespace PhpProxyHunter;

use Exception;
use SessionHandlerInterface;

class FileSessionHandler implements SessionHandlerInterface
{
  protected $sess_path;
  protected $prefix;
  protected $postfix;

  /**
   * @throws Exception
   */
  public function __construct($sess_path, $prefix = 'sess_', $postfix = '')
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

  public function open($save_path, $sess_name): bool
  {
    return true;
  }

  public function close(): bool
  {
    return true;
  }

  public function read($sess_id): string
  {
    return (string)@file_get_contents("{$this->sess_path}/{$this->prefix}{$sess_id}{$this->postfix}");
  }

  public function write($sess_id, $data): bool
  {
    return !(false === file_put_contents("{$this->sess_path}/{$this->prefix}{$sess_id}{$this->postfix}", $data));
  }

  public function destroy($sess_id): bool
  {
    $file = "{$this->sess_path}/{$this->prefix}{$sess_id}{$this->postfix}";

    if (file_exists($file)) {
      unlink($file);
    }

    return true;
  }

  public function gc($lifetime): bool
  {
    foreach (glob("{$this->sess_path}/{$this->prefix}*") as $file) {
      if (filemtime($file) + $lifetime < time() && file_exists($file)) {
        unlink($file);
      }
    }

    return true;
  }
}
