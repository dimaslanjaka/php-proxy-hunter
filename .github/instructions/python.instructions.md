---
applyTo: '**/*.py'
---

Coding standards, domain knowledge, and preferences that AI should follow.

## General Guidelines

- Write clear, concise, and well-documented Python code.
- Follow [PEP 8](https://pep8.org/) style guidelines.
- Format all code using the [Black](https://black.readthedocs.io/en/stable/) code formatter.
- Use type hints where appropriate.
- Prefer list comprehensions and generator expressions for concise code.
- Handle exceptions gracefully and log errors when necessary.
- Write functions and classes with single responsibilities.

## Code Structure

- Organize code into modules and packages.
- Place reusable code in the `src/` or `project/` directory.
- Keep tests in a `tests/` directory, mirroring the source structure.

## Dependencies

- List all dependencies in `requirements.txt`.
- Use virtual environments for dependency management.

## Testing

- Write unit tests for all functions and classes.
- Use `pytest` as the default testing framework.
- Ensure tests are isolated and do not depend on external state.

## Documentation

- Document all public functions, classes, and modules using docstrings.
- Provide usage examples where helpful.
- Maintain a `README.md` with setup and usage instructions.

## Version Control

- Commit code frequently with clear, descriptive messages.
- Do not commit secrets or sensitive data.
- Use `.gitignore` to exclude unnecessary files.

## Code Review

- Submit pull requests for all changes.
- Address code review feedback promptly.
- Ensure all tests pass before merging.

---
**These instructions are intended for use with GitHub Copilot and contributors to this Python project.**