<?php

namespace PhpProxyHunter;

/**
 * Class ExecutionTimer
 *
 * A helper to manage script execution time and avoid timeouts.
 */
class ExecutionTimer
{
  /**
   * The time when the timer started.
   *
   * @var float
   */
  protected float $startTime;

  /**
   * Maximum execution time in seconds.
   *
   * @var int
   */
  protected int $maxExecutionTime;

  /**
   * Safety buffer in seconds to avoid hitting the limit exactly.
   *
   * @var int
   */
  protected int $safetyBuffer;

  /**
   * ExecutionTimer constructor.
   *
   * @param int $maxExecutionTime Maximum allowed execution time in seconds.
   * @param int $safetyBuffer Safety buffer time in seconds.
   */
  public function __construct(int $maxExecutionTime = 30, int $safetyBuffer = 2)
  {
    $this->startTime = microtime(true);
    $this->maxExecutionTime = $maxExecutionTime;
    $this->safetyBuffer = $safetyBuffer;
  }

  /**
   * Determines if the script should exit to avoid timeout.
   *
   * @return bool True if elapsed time exceeds threshold, otherwise false.
   */
  public function shouldExit(): bool
  {
    $elapsed = microtime(true) - $this->startTime;
    return $elapsed >= ($this->maxExecutionTime - $this->safetyBuffer);
  }

  /**
   * Exits the script if the time limit is about to be reached.
   *
   * @param string $message Message to display before exiting.
   * @return void
   */
  public function exitIfNeeded(string $message = 'Script terminated to avoid timeout.'): void
  {
    if ($this->shouldExit()) {
      echo $message . PHP_EOL;
      exit(1);
    }
  }

  /**
   * Gets the elapsed time since the timer started.
   *
   * @return float Elapsed time in seconds.
   */
  public function getElapsedTime(): float
  {
    return microtime(true) - $this->startTime;
  }

  /**
   * Gets the remaining time before the timeout threshold is reached.
   *
   * @return float Remaining time in seconds.
   */
  public function getRemainingTime(): float
  {
    return max(0, ($this->maxExecutionTime - $this->safetyBuffer) - $this->getElapsedTime());
  }
}
