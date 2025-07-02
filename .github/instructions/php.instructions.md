---
applyTo: '**'
---

Coding standards, domain knowledge, and preferences that AI should follow.

- Use PSR-12 coding standards for all PHP code.
- Prefer strict types; always declare `declare(strict_types=1);` at the top of PHP files.
- Use type hints for all function parameters and return types where possible.
- Use namespaces appropriately to organize code.
- Prefer short array syntax (`[]`) over `array()`.
- Use meaningful variable and function names.
- Write PHPDoc comments for all public classes, methods, and properties.
- Avoid using global variables.
- Use dependency injection instead of creating new instances inside classes.
- Write unit tests for all new features and bug fixes.