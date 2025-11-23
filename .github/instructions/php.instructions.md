---
applyTo: '**/*.php'
---

# GitHub Copilot Instructions — PHP

This project uses `composer` for dependency management and follows modern PHP development practices.

## Coding standards, domain knowledge, and preferences that AI should follow.

- Use PSR-12 coding standards for all PHP code.
- PHP version minimum: 7.0.
- Use type hints for all function parameters and return types where possible.
- Use namespaces appropriately to organize code.
- Prefer short array syntax (`[]`) over `array()`.
- Use meaningful variable and function names.
- Write PHPDoc comments for all public classes, methods, and properties.
- Dont use type hint for callable in register method for compatibility with PHP 7.0.
- Dont use return type hint for methods that return mixed types or when compatibility with older PHP versions is required.
- Dont use type hint for parameters for compatibility with PHP 7.0.
- Instead of using type hints, use PHPDoc comments to indicate expected types for better compatibility with PHP 7.0.
