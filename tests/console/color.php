<?php

require_once __DIR__ . '/../bootstrap.php';

use PhpProxyHunter\AnsiColors;

// formatPrint and formatPrintLn removed; use echo directly

// Examples:
echo AnsiColors::colorize(['blue', 'bold', 'italic', 'strikethrough'], 'Wohoo');
echo ' HTML: ' . AnsiColors::ansiToHtml(AnsiColors::colorize(['blue', 'bold', 'italic', 'strikethrough'], 'Wohoo')) . "\r\n";

echo AnsiColors::colorize(['yellow', 'italic'], " I'm invicible");
echo "\r\n";
echo ' HTML: ' . AnsiColors::ansiToHtml(AnsiColors::colorize(['yellow', 'italic'], " I'm invicible")) . "\r\n";

echo AnsiColors::colorize(['yellow', 'bold'], "I'm invicible");
echo "\r\n";
echo ' HTML: ' . AnsiColors::ansiToHtml(AnsiColors::colorize(['yellow', 'bold'], "I'm invicible")) . "\r\n";

$buildStr = 'Hello ' . AnsiColors::colorize(['red', 'bold'], 'World') . '!';
echo $buildStr . "\r\n";
echo 'HTML: ';
echo AnsiColors::ansiToHtml($buildStr) . "\r\n";
