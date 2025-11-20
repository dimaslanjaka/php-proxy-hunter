const fs = require('fs');
const path = require('path');
const { spawn } = require('child_process');

// Build the content block
const content = `<?php

for ($i = 0; $i < 10; $i++) {
    echo $i . "\\n";
}

for ($j = 0; $j < 5; $j++) {
    echo $j . "\\n";
}

if (isset($tokens)) unset($tokens);
$tokens = []; gc_collect_cycles();

for ($index = $tokens->count() - 1; $index >= 0; $index--) {
      $token = $tokens[$index];
      echo $token->getContent() . "\\n";
}
`;

// Output file
const outputFile = path.join(__dirname, 'test-input.txt');
fs.writeFileSync(outputFile, content);

// Detect windows
const isWin = process.platform === 'win32';

// vendor/bin is three levels up
const vendorBin = path.resolve(__dirname, '../../../vendor/bin');

if (!fs.existsSync(vendorBin)) {
  console.error(`vendor/bin not found, attempted: ${path.join(__dirname, '../../../vendor/bin')}`);
  process.exit(1);
}

// Select correct php-cs-fixer wrapper for Windows
let phpCsFixerCmd;
if (isWin) {
  // Run the PHP wrapper: php vendor/bin/php-cs-fixer
  phpCsFixerCmd = ['php', path.join(vendorBin, 'php-cs-fixer')];
} else {
  // Execute directly
  phpCsFixerCmd = [path.join(vendorBin, 'php-cs-fixer')];
}

// Full fixer command arguments
const args = ['fix', outputFile, '--diff', '--dry-run', '-vvv'];

console.log('Running command:');
console.log(`  ${phpCsFixerCmd.join(' ')} ${args.join(' ')}\n`);

// Spawn diffs with live output
const proc = spawn(phpCsFixerCmd[0], [...phpCsFixerCmd.slice(1), ...args], {
  stdio: 'inherit',
  shell: true
});

proc.on('exit', (code) => {
  console.log(`\nProcess exited with code ${code}`);
});

// Spawn without dry-run to apply fixes
proc.on('close', () => {
  console.log('\nApplying fixes...\n');

  const applyProc = spawn(phpCsFixerCmd[0], [...phpCsFixerCmd.slice(1), 'fix', outputFile, '-vvv'], {
    stdio: 'inherit',
    shell: true
  });

  applyProc.on('exit', (code) => {
    console.log(`\nFix process exited with code ${code}`);
  });
});
