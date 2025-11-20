<?php

namespace PhpCsFixerCustom;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\Tokenizer\Tokens;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\FixerDefinition\FixerDefinition;

final class GlobalOneLineFixer extends AbstractFixer {
  public function getName(): string {
    return 'Custom/global_one_line';
  }

  public function getDefinition(): FixerDefinitionInterface {
    return new FixerDefinition(
      'Convert multi-line global statements into a one-liner.',
      []
    );
  }

  public function isCandidate(Tokens $tokens): bool {
    return $tokens->isTokenKindFound(T_GLOBAL);
  }

  public function applyFix(\SplFileInfo $file, Tokens $tokens): void {
    for ($i = 0; $i < $tokens->count(); $i++) {
      if (!$tokens[$i]->isGivenKind(T_GLOBAL)) {
        continue;
      }

      $start = $i;
      $end   = $tokens->getNextTokenOfKind($i, [';']);
      if ($end === null) {
        continue;
      }

      // collect variables (strip trailing commas and ignore empty/whitespace)
      $vars = [];
      for ($j = $start + 1; $j < $end; $j++) {
        $text = trim($tokens[$j]->getContent());
        $text = rtrim($text, ',');
        if ($text !== '' && $text !== "\n") {
          $vars[] = $text;
        }
      }

      if (count($vars) === 0) {
        continue;
      }

      // build token sequence: T_GLOBAL <space> $var (, <space> $var)* ;
      $newTokens   = [];
      $newTokens[] = new Token([T_GLOBAL, 'global']);
      $newTokens[] = new Token([T_WHITESPACE, ' ']);

      $first = true;
      foreach ($vars as $v) {
        if (!$first) {
          $newTokens[] = new Token(',');
          $newTokens[] = new Token([T_WHITESPACE, ' ']);
        }
        // ensure it is a variable token
        if ($v !== '' && $v[0] === '$') {
          $newTokens[] = new Token([T_VARIABLE, $v]);
        } else {
          $newTokens[] = new Token([T_STRING, $v]);
        }
        $first = false;
      }

      $newTokens[] = new Token(';');

      $tokens->overrideRange($start, $end, $newTokens);
    }
  }
}
