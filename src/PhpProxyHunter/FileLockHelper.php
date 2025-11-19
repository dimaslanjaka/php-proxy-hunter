<?php

namespace PhpProxyHunter;

class FileLockHelper {
  public string $filePath;
  private $handle;
  private int $lockType;

  /**
   * Constructor.
   *
   * @param string $filePath Path to the lock file.
   */
  public function __construct(string $filePath) {
    $this->filePath = $filePath;
  }

  /**
   * Acquire a lock.
   *
   * @param int $lockType Use LOCK_EX for exclusive, LOCK_SH for shared.
   * @return bool True on success, false on failure.
   */
  public function lock(int $lockType = LOCK_EX): bool {
    $this->handle = $this->openLockHandle();
    if ($this->handle === false) {
      return false;
    }

    $this->lockType = $lockType;
    if (!flock($this->handle, $lockType)) {
      fclose($this->handle);
      $this->handle = null;
      return false;
    }

    return true;
  }

  /**
   * Unlock and close the file handle.
   *
   * @return void
   */
  public function unlock(): void {
    if (is_resource($this->handle)) {
      flock($this->handle, LOCK_UN);
      fclose($this->handle);
      $this->handle = null;
      // delete the lock file gracefully
      @unlink($this->filePath);
    }
  }

  /**
   * Release the lock.
   *
   * @return void
   */
  public function release(): void {
    $this->unlock();
  }

  /**
   * Check if the lock file currently exists.
   *
   * @return bool
   */
  public function isLocked(): bool {
    // Attempt an exclusive non-blocking lock. This will fail if any other
    // process holds either a shared or exclusive lock on the file, so it
    // conservatively indicates "locked by someone else".
    $handle = $this->openLockHandle();
    if ($handle === false) {
      // Can't open the file — fall back to file existence check
      return file_exists($this->filePath);
    }

    $isLocked = !flock($handle, LOCK_EX | LOCK_NB);
    if (!$isLocked) {
      // We acquired the exclusive lock; release it immediately
      flock($handle, LOCK_UN);
    }

    fclose($handle);
    return $isLocked;
  }

  /**
   * Open the lock file handle, ensuring the directory exists and is writable.
   * Returns a resource (handle) on success or false on failure.
   *
   * @return resource|false
   */
  private function openLockHandle() {
    $dir = dirname($this->filePath);

    if (!is_dir($dir)) {
      if (!@mkdir($dir, 0777, true) && !is_dir($dir)) {
        return false;
      }
    }

    if (!is_writable($dir)) {
      return false;
    }

    $handle = @fopen($this->filePath, 'c+');
    if ($handle === false) {
      return false;
    }

    return $handle;
  }

  /**
   * Automatically unlock on destruction.
   */
  public function __destruct() {
    $this->unlock();
  }
}

// Only run when executed directly from CLI, not when included or required
if (php_sapi_name() === 'cli' && realpath(__FILE__) === realpath($_SERVER['argv'][0] ?? '')) {
  $lockFile = __DIR__ . '/mylock.lock';
  $lock     = new FileLockHelper($lockFile);

  if ($lock->lock(LOCK_EX)) {
    echo "Lock acquired {$lock->filePath}. Starting work...\n";

    // Simulate work loop with periodic output
    for ($i = 1; $i <= 5; $i++) {
      echo "Working... step $i\n";
      if ($lock->isLocked()) {
        echo "The lock file is in use by another process.\n";
      } else {
        echo "The lock file is free.\n";
      }
      if ($lock->isLocked()) {
        echo "The lock file exists.\n";
      } else {
        echo "The lock file does not exist.\n";
      }
      sleep(1); // Simulate task delay
    }

    echo "Work done. Releasing lock...\n";
  // No need to manually call unlock() — __destruct will handle it
  } else {
    echo "Another instance is already running. Exiting.\n";
    exit(1);
  }
}
