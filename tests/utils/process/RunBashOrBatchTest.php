<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../src/utils/process/runBashOrBatch.php';

class RunBashOrBatchTest extends TestCase
{
  public function tearDown(): void
  {
    // clean up both tmp/logs and repo-level logs (tests may write to either)
    @unlink(tmp() . '/logs/sample-error-status.txt');
    @unlink(tmp() . '/logs/sample-error.txt');
    @unlink(__DIR__ . '/../../../logs/sample-error-status.txt');
    @unlink(tmp() . '/logs/sample-error-runBashOrBatch.txt');
  }

  public function testPhpScriptErrorIsCaptured()
  {
    $script = __DIR__ . '/sample-error.php';
    // Create a runner script that invokes PHP on the sample script, then run it
    $runner = createBatchOrBashRunner('sample-error', 'php ' . escapeshellarg($script));
    // Run with redirection enabled so stdout/stderr go into the log
    $res = runBashOrBatch($runner, [], 'sample-error', true);

    // Wait 5 seconds for the background process to start
    sleep(5);

    $message = isset($res['message']) ? (is_string($res['message']) ? $res['message'] : json_encode($res['message'])) : '';
    $this->assertFalse($res['error'] ?? true, 'runBashOrBatch reported an error: ' . $message);

    $data = json_decode($res['message'], true);
    $this->assertArrayHasKey('output', $data);
    $outputFile = $data['output'];

    // Wait briefly for background process to run and write files. The sample
    // script writes its status under the repository `logs/` while the
    // runner's redirected output is placed in tmp()/logs/.
    $statusPath = __DIR__ . '/../../../logs/sample-error-status.txt';
    $tries      = 0;
    while ($tries++ < 20 && (!file_exists($outputFile) || !file_exists($statusPath))) {
      usleep(100 * 1000); // 100ms
    }

    $this->assertFileExists($outputFile, 'Output log file was not created');
    $log = file_get_contents($outputFile);

    $this->assertStringContainsString('OUT:hello', $log, 'stdout not found in log');
    $this->assertStringContainsString('ERR:boom', $log, 'stderr not found in log');

    $status = file_get_contents($statusPath);
    $this->assertEquals('5', trim($status), 'Status file did not contain expected exit code');
  }
}
