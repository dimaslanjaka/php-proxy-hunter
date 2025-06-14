<?php

use PHPUnit\Framework\TestCase;
use PhpProxyHunter\FileLockHelper;

// phpunit tests/PhpProxyHunter/FileLockHelperTest.php

class FileLockHelperTest extends TestCase
{
  private string $lockFile;

  protected function setUp(): void
  {
    $this->lockFile = sys_get_temp_dir() . '/php_lock_test/mylock.lock';
    if (file_exists($this->lockFile)) {
      unlink($this->lockFile);
    }
  }

  protected function tearDown(): void
  {
    if (file_exists($this->lockFile)) {
      unlink($this->lockFile);
    }

    $dir = dirname($this->lockFile);
    if (is_dir($dir)) {
      @rmdir($dir);
    }
  }

  public function testLockCreatesFile(): void
  {
    $lock = new FileLockHelper($this->lockFile);
    $this->assertTrue($lock->lock());
    $this->assertFileExists($this->lockFile);
    $lock->unlock();
  }

  public function testIsLockedReturnsTrueAfterLock(): void
  {
    $lock = new FileLockHelper($this->lockFile);
    $lock->lock();
    $this->assertTrue($lock->isLocked());
    $lock->unlock();
  }

  public function testUnlockReleasesLock(): void
  {
    $lock = new FileLockHelper($this->lockFile);
    $lock->lock();
    $lock->unlock();
    $this->assertFalse($lock->isLockedByAnotherProcess());
  }

  public function testIsLockedByAnotherProcessDetectsLock(): void
  {
    $lock1 = new FileLockHelper($this->lockFile);
    $this->assertTrue($lock1->lock(), 'Lock 1 should be acquired');

    $lock2 = new FileLockHelper($this->lockFile);
    $this->assertTrue(
      $lock2->isLockedByAnotherProcess(),
      'Lock 2 should detect that the file is locked by another process'
    );

    $lock1->unlock();
    $this->assertFalse(
      $lock2->isLockedByAnotherProcess(),
      'Lock 2 should detect that the lock file is free after unlocking'
    );
  }

  public function testAutoUnlockOnDestruct(): void
  {
    $lock1 = new FileLockHelper($this->lockFile);
    $this->assertTrue($lock1->lock());

    unset($lock1); // triggers __destruct()

    $lock2 = new FileLockHelper($this->lockFile);
    $this->assertFalse($lock2->isLockedByAnotherProcess());
  }
}
