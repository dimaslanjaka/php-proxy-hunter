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

  public function open($path, $name): bool
  {
    return true;
  }

  public function close(): bool
  {
    return true;
  }

  public function read($id): string
  {
    return (string)@file_get_contents("{$this->sess_path}/{$this->prefix}{$id}{$this->postfix}");
  }

  public function write($id, $data): bool
  {
    return !(false === file_put_contents("{$this->sess_path}/{$this->prefix}{$id}{$this->postfix}", $data));
  }

  public function destroy($id): bool
  {
    $file = "{$this->sess_path}/{$this->prefix}{$id}{$this->postfix}";

    if (file_exists($file)) {
      unlink($file);
    }

    return true;
  }

  public function gc($max_lifetime): bool
  {
    foreach (glob("{$this->sess_path}/{$this->prefix}*") as $file) {
      if (filemtime($file) + $max_lifetime < time() && file_exists($file)) {
        unlink($file);
      }
    }

    return true;
  }
}