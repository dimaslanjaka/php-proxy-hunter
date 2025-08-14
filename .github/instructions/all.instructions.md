---
applyTo: '**/*.*'
---

- Git commit messages should be clear and concise, following the conventional commits format.
- When running shell commands that produce output files, direct these outputs to the `tmp/output-shell` directory and review the complete results for thorough debugging, especially for long-running processes.
- All projects test folders should be named `tests`.
- Moving files should be done with care, ensuring that the new location is correctly referenced in the codebase.
- When creating or modifying files, ensure that the file paths are correct and that the files are placed in appropriate directories.
- Always check for existing files before creating new ones to avoid overwriting important data.
