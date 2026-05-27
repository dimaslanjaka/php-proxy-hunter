# AGENTS Guide

## Project map (read this first)

- This is a mixed-stack proxy toolkit: PHP runtime + Python workers + Node/React UI/build tooling (`readme.md`, `package.json`, `composer.json`, `pyproject.toml`).
- Main web entry is `index.php` (serves `dist/react/index.html`, static assets, maintenance lock at `tmp/locks/.build-lock`).
- Proxy checking flow from UI: `proxyCheckerBackground.php` -> background `php proxyChecker.php` -> updates DB/files (`proxyChecker.txt`, `status.txt`, `working.txt`, `dead.txt`) (previously used `proxyManager.js`, removed).
- Parallel checker flow: `proxyCheckerParallel.php`/`proxyCheckerParallel-func.php` with per-runner lock files in `tmp/runners/`.
- Core persistence abstraction is `src/PhpProxyHunter/ProxyDb.php`; runtime DB type is selected in `php_backend/shared.php` (GitHub CI forces SQLite, otherwise MySQL by env).

# Project Architecture

This is a React application with:

- Frontend Components in `/src/react`
- PHP backends in `/php_backend`

## Coding Standards

- Use TypeScript for all new files
- Follow the existing naming conventions
- Write tests for all new features

## Data and state you must respect

- Proxy pool files are part of runtime state: `proxies.txt`, `working.txt`, `dead.txt`, `status.txt`, `status.json`, plus `assets/proxies/added-*.txt` ingestion.
- `proxyChecker.lock` prevents duplicate runs; many scripts exit early when this lock exists.
- `src/database.sqlite` is the default local DB; schema/migrations are applied from `assets/database/create.sql` and `ProxyDBMigration`.
- `src/autoload.php` recursively requires many PHP files; adding global functions can have wide side effects.

## Developer workflows (project-specific)

- Preferred bootstrap is Task: `task install-nodejs`, `task install-python`, `task install-php` (see `Taskfile.yml`).
- On Windows, Python commands are expected through `bin/py.cmd` wrapper (auto-creates/activates `venv`).
- Python deps are generated+installed via `requirements_install.py` (do not hand-edit generated `requirements.txt` unless necessary).
- Frontend build path is Vite to `dist/react` (`npm run build:react`), while Node bundles use Rollup configs (`rollup.project.js`, `rollup.php.js`, `rollup.whatsapp.js`).
- Local PHP app start is `npm start` -> `php -S 0.0.0.0:4000`.

## Testing and checks

- JS/TS tests: `npm test` (Jest, config in `jest.config.ts`), optional UI tests via Vitest (`vitest.config.ts`).
- PHP tests: `vendor/bin/phpunit -c phpunit.xml` (bootstrap `tests/bootstrap.php`).
- Python tests: `pytest` (configured in `pyproject.toml`, `tests/` as root).
- Lint/style: `npm run lint`, PHPCS uses `phpcs.xml` (2-space indent, PSR-12 relaxed in several places).

## Code conventions observed here

- PHP code intentionally allows side-effect files and non-strict naming in many areas (`phpcs.xml` disables several naming/side-effect sniffs).
- JS linting ignores many generated/non-source trees (`eslint.config.js`); avoid editing `public/` copies directly when source exists in root.
- Checker scripts are designed for long-running background execution with lock/scheduler cleanup (`PhpProxyHunter\Scheduler`, `src/scheduler.py`).
- Database writes are often defensive/upsert-like (`ProxyDB::add`, `updateData`, `markAsAdded`) to dedupe proxy ingestion.

## Integration boundaries

- External network dependencies are first-class: checker/fetcher scripts call many remote proxy sources and target endpoints from user config (`getConfig()` in checker scripts).
- PHP and Python share domain concepts (proxy extraction/checking) but not always identical entrypoints; prefer editing the stack-specific source used by the invoked script.
- Django exists as a separate backend entry (`manage.py`, `django_backend/`), with background queue usage documented via `manage.py run_huey` in `readme-python.md`.
- Yarn workspaces under `packages/*` contain reusable libs (`proxy-hunter-python`, `proxy-checker-python`, etc.); install tasks link these editable/local packages.
