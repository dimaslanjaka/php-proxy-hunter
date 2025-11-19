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
    // Ensure the directory exists before trying to open the file
    $dir = dirname($this->filePath);
    if (!is_dir($dir)) {
      if (!@mkdir($dir, 0777, true) && !is_dir($dir)) {
        // Failed to create directory (permissions or race) — cannot proceed
        return false;
      }
    }

    // Check writability where possible. On some Windows setups this may be limited,
    // so we check and proceed to a safe fopen attempt.
    if (!is_writable($dir)) {
      // Directory exists but isn't writable for the PHP process
      return false;
    }

    // Suppress warnings from fopen and handle failure explicitly to avoid PHP warnings
    $this->handle = @fopen($this->filePath, 'c+');
    if ($this->handle === false) {
      return false;
    }

    $this->lockType = $lockType;
    if (!flock($this->handle, $lockType)) {
      // Could not acquire lock — close handle and return false
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
    return file_exists($this->filePath);
  }

  /**
   * Check if the lock file is currently locked by another process.
   *
   * @return bool True if locked by someone else, false otherwise.
   */
  public function isLockedByAnotherProcess(): bool {
    // Ensure the lock file's directory exists
    $dir = dirname($this->filePath);
    if (!is_dir($dir)) {
      if (!@mkdir($dir, 0777, true) && !is_dir($dir)) {
        // Can't ensure the directory — assume not locked (conservative)
        return false;
      }
    }

    // If directory is not writable, we likely cannot create/open the lock file.
    if (!is_writable($dir)) {
      return false;
    }

    // Suppress fopen warnings and handle failure explicitly
    $tempHandle = @fopen($this->filePath, 'c+');
    if ($tempHandle === false) {
      return false; // Can't open the file; assume not locked
    }

    $locked = !flock($tempHandle, LOCK_EX | LOCK_NB);

    if (!$locked) {
      // Not locked — release immediately
      flock($tempHandle, LOCK_UN);
    }

    fclose($tempHandle);
    return $locked;
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
      if ($lock->isLockedByAnotherProcess()) {
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
