# tailscale — repository utilities and files

This folder contains small utilities and quick scripts that collect and expose Tailscale status information and help test connectivity to services reachable via a Tailscale IP.

Summary of files

- `index.php` — HTTP endpoint that returns the contents of `tmp/data/tailscale.json` if present. It attempts JSON decode and returns either parsed JSON or the raw file content under the `data` field.
- `utils.py` — main helpers used by scripts in this folder and other repo parts. Important functions:
  - `save_tailscale_status(local_path=None, tailscale_cmd='tailscale')` — runs `tailscale status --json` and writes the result to `tmp/data/tailscale.json` (or `local_path`). Returns the Path written.
  - `get_tailscale_status(tailscale_cmd='tailscale')` — calls `tailscale status --json` and returns parsed JSON.
  - `get_tailscale_ipv4(local_path=None, tailscale_cmd='tailscale')` — returns the first IPv4 address found in the status JSON (from file or live call).
  - `upload_tailscale_status(local_path=None, remote_filename='tailscale.json', port=22)` — uploads the saved JSON file to a remote SFTP server. Reads `SFTP_HOST`, `SFTP_USER`, `SFTP_PASS`, and optional `SFTP_PATH` from environment.
  - `save_firebase_database(input_data=None)` — pushes the tailscale payload to a Firebase Realtime Database using `tailscale/firebase-adminsdk.json` credentials.
- `mysql-test.py` — convenience script that:
  1. updates/saves tailscale status and optionally uploads it;
  2. obtains the tailscale IPv4 with `get_tailscale_ipv4()`;
  3. reads DB config from `src.shared.get_db_config()` and attempts a MySQL connection using `src.MySQLHelper.MySQLHelper` to `IP:3306` and prints success/failure.
- `firebase-adminsdk.json` — Firebase service account used by `save_firebase_database` (sensitive; do not commit publicly).

Prerequisites

- Tailscale CLI available on PATH for live queries (`tailscale status --json`).
- Python deps: `paramiko`, `firebase-admin` (install in your virtualenv). The scripts also import local repo modules (`src.*`, `proxy_hunter`, etc.), so run them from the repository root or set `PYTHONPATH` appropriately.
- Environment variables for SFTP uploads: `SFTP_HOST`, `SFTP_USER`, `SFTP_PASS`, optional `SFTP_PATH`.

Quick usage

- Save live tailscale status:

  python -c "from tailscale.utils import save_tailscale_status; save_tailscale_status()"

- Upload saved status via SFTP:

  python -c "from tailscale.utils import upload_tailscale_status; upload_tailscale_status()"

- Run the MySQL test script:

  python tailscale/mysql-test.py

- Serve `index.php` from a webserver rooted at repo root; it will read and return `tmp/data/tailscale.json`.

Notes & security

- Keep `firebase-adminsdk.json` private.
- Do not include production credentials in saved JSON files or when pushing to external services.
- Use Tailscale ACLs and application-level auth when exposing services publicly.

Next steps

- I can add a small CLI wrapper in this folder to run `save_tailscale_status()` and optionally upload/push to Firebase with flags, or add example `nginx`/`Caddy` reverse-proxy snippets. Let me know which you prefer.
