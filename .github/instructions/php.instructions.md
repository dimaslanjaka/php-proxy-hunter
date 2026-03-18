---
applyTo: '**/*.php'
---

# GitHub Copilot Instructions — PHP

This project uses `composer` for dependency management and follows modern PHP development practices.

## Coding standards, domain knowledge, and preferences that AI should follow.

- Use PSR-12 coding standards for all PHP code.
- PHP version minimum: 8.1.
- Use type hints for all function parameters and return types where possible.
- Use namespaces appropriately to organize code.
- Prefer short array syntax (`[]`) over `array()`.
- Use meaningful variable and function names.
- Write PHPDoc comments for all public classes, methods, and properties.
- Use typed properties and union types when they improve clarity and remain compatible with PHP 8.1.
- Prefer native type declarations over PHPDoc-only typing when possible.
- Avoid using syntax/features that require PHP versions newer than 8.1 unless explicitly requested.
