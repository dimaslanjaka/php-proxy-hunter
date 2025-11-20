<?php

namespace PhpCsFixerCustom;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\Tokenizer\Tokens;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;

final class CustomNoMultipleStatementsPerLineFixer extends AbstractFixer {
  public function getName(): string {
    return 'Custom/no_multiple_statements_per_line';
  }

  public function getDefinition(): FixerDefinitionInterface {
    return new FixerDefinition(
      'Ensure each statement ends with a semicolon and resides on its own physical line (do not split for(...) headers).',
      []
    );
  }

  /**
   * Run before most whitespace/indentation fixers.
   * Higher value => runs earlier.
   */
  public function getPriority(): int {
    return 500;
  }

  public function isCandidate(Tokens $tokens): bool {
    return $tokens->isTokenKindFound(';');
  }

  public function applyFix(\SplFileInfo $file, Tokens $tokens): void {
    // Walk backwards so insertions don't shift future indexes.
    for ($index = $tokens->count() - 1;
    $index >= 0;
    $index--) {
      $token = $tokens[$index];

      // semicolons are tokens with content ';'
      if ($token->getContent() !== ';') {
        continue;
      }

      // If this semicolon belongs to a for(...) header, skip.
      if ($this->isSemicolonInForHeader($tokens, $index)) {
        continue;
      }

      // If it belongs to declare(...) or is part of a for-like construct skip.
      if ($this->isSemicolonInDeclare($tokens, $index)) {
        continue;
      }

      // If semicolon is followed by a newline already — nothing to do.
      $after = $index + 1;
      if (isset($tokens[$after])) {
        $afterContent = $tokens[$after]->getContent();
        if (strpos($afterContent, "\n") !== false) {
          continue;
        }

        // Insert newline but preserve indentation: find indentation of current line
        $indent = $this->detectIndentationBefore($tokens, $index);
        $tokens->ensureWhitespaceAtIndex($after, 0, "\n" . $indent);
      } else {
        // semicolon at the end, append newline
        $tokens->ensureWhitespaceAtIndex($after, 0, "\n");
      }
    }
  }

  /**
   * Try to detect indentation (spaces/tabs) of the current statement so we can preserve it after newline.
   */
  private function detectIndentationBefore(Tokens $tokens, int $index): string {
    // Look backwards for the last newline in the token contents and return whatever whitespace follows it.
    for ($i = $index; $i >= 0; $i--) {
      $content = $tokens[$i]->getContent();
      $pos     = strrpos($content, "\n");
      if ($pos !== false) {
        // return whitespace after newline (indentation)
        $rest = substr($content, $pos + 1);
        // keep only leading whitespace
        preg_match('/^[ \t]*/', $rest, $m);
        return $m[0] ?? '';
      }
    }

    // fallback: no newline found — no indentation
    return '';
  }

  /**
   * Detect if semicolon is inside a `for(...)` header.
   *
   * Uses block-aware scanning (findBlockEnd) and a wider scan backwards to detect
   * a 'for' keyword before the opening parenthesis, tolerating comments or whitespace.
   */
  private function isSemicolonInForHeader(Tokens $tokens, int $semicolonIndex): bool {
    // Iterate previous '(' tokens until we find the one whose matching ')' encloses the semicolon.
    // This avoids selecting inner parentheses (like function calls) that would incorrectly exclude
    // semicolons belonging to the surrounding for(...) header.
    $searchIndex = $semicolonIndex;
    $attempts    = 0;
    while (true) {
      $open = $tokens->getPrevTokenOfKind($searchIndex, ['(']);
      if ($open === null) {
        return false;
      }

      // try to find the matching closing parenthesis for this '('
      try {
        $close = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_PARENTHESIS_BRACE, $open);
      } catch (\RuntimeException $e) {
        // malformed block, skip this '(' and continue searching
        $searchIndex = $open - 1;
        $attempts++;
        if ($attempts > 50) {
          return false;
        }
        continue;
      }

      // If the semicolon is inside this parentheses block, we found the candidate.
      if ($open < $semicolonIndex && $semicolonIndex < $close) {
        $beforeOpen = $tokens->getPrevMeaningfulToken($open);
        if ($beforeOpen === null) {
          return false;
        }

        if ($tokens[$beforeOpen]->isGivenKind(T_FOR)) {
          return true;
        }

        return false;
      }

      // otherwise continue searching for an earlier '('
      $searchIndex = $open - 1;
      $attempts++;
      if ($attempts > 50) {
        return false;
      }
    }
  }

  /**
   * Detect whether semicolon belongs to a declare(...) construct.
   * For "declare(...);" we don't want to split.
   */
  private function isSemicolonInDeclare(Tokens $tokens, int $semicolonIndex): bool {
    $prev = $tokens->getPrevMeaningfulToken($semicolonIndex);
    if ($prev === null) {
      return false;
    }

    return $tokens[$prev]->isGivenKind(T_DECLARE);
  }
}
