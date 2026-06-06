#!/usr/bin/env python3
import os
import sys
import shlex
import subprocess
from pathlib import Path
from typing import List, Optional

_SCRIPT = Path(__file__).name


def _help_text() -> str:
    """Build help text with dynamic script name."""
    return f"""\
Generic runner wrapper for Node.js, PHP, and Python scripts.

Usage:
  python {_SCRIPT} [runner-options] <file> [forwarded-args...]

Examples:
  python {_SCRIPT} folder/script.php --cli-php-script=anyValue
  python {_SCRIPT} folder/script.js --name=test
  python {_SCRIPT} folder/script.py --foo bar

Keep new CMD open:
  python {_SCRIPT} -k folder/script.php --cli-php-script=anyValue

Run in same terminal:
  python {_SCRIPT} -s folder/script.js --debug

Force runtime manually:
  python {_SCRIPT} --runtime node folder/script.js --foo=bar
  python {_SCRIPT} --runtime php folder/script.php --foo=bar
  python {_SCRIPT} --runtime python folder/script.py --foo=bar

Use custom binary:
  python {_SCRIPT} --node node folder/script.js
  python {_SCRIPT} --php php folder/script.php
  python {_SCRIPT} --python py folder/script.py

Runner options:
  -k, --keep-open          Use cmd /k, keep CMD open after script exits
  --cmd-mode c|k           Use cmd /c or cmd /k
  -s, --same-terminal      Run in current terminal instead of opening new CMD
  -r, --runtime NAME       auto, node, php, python
  --node PATH              Node executable, default: node
  --php PATH               PHP executable, default: php
  --python PATH            Python executable, default: current Python
  --cwd DIR                Working directory
  --dry-run                Print command without running
  -h, --help               Show this help

Important:
  Only options before <file> are parsed as runner options.
  Everything after <file> is forwarded untouched to the target script.
"""


NODE_EXTENSIONS = {".js", ".mjs", ".cjs"}
PHP_EXTENSIONS = {".php"}
PYTHON_EXTENSIONS = {".py"}


class RunnerArgs:
    def __init__(self) -> None:
        self.runtime = "auto"
        self.target_file: Optional[str] = None
        self.forwarded_args: List[str] = []

        self.node_bin = os.environ.get("NODE_BIN", "node")
        self.php_bin = os.environ.get("PHP_BIN", "php")
        self.python_bin = os.environ.get("PYTHON_BIN", sys.executable)

        self.same_terminal = False
        self.keep_open = False
        self.cmd_mode = "c"
        self.cwd: Optional[str] = None
        self.dry_run = False


def print_error(message: str) -> None:
    print(f"❌ {message}")


def parse_args(argv: List[str]) -> RunnerArgs:
    parsed = RunnerArgs()

    if not argv:
        print(_help_text())
        sys.exit(0)

    i = 0

    while i < len(argv):
        arg = argv[i]

        if arg == "--":
            i += 1

            if i >= len(argv):
                print_error("Missing target file after --")
                sys.exit(1)

            parsed.target_file = argv[i]
            parsed.forwarded_args = argv[i + 1 :]
            return parsed

        if arg in ("-h", "--help"):
            print(_help_text())
            sys.exit(0)

        if arg in ("-k", "--keep-open"):
            parsed.keep_open = True
            parsed.cmd_mode = "k"
            i += 1
            continue

        if arg in ("-s", "--same-terminal"):
            parsed.same_terminal = True
            i += 1
            continue

        if arg == "--dry-run":
            parsed.dry_run = True
            i += 1
            continue

        if arg in ("-r", "--runtime"):
            i += 1

            if i >= len(argv):
                print_error("Missing value for --runtime")
                sys.exit(1)

            runtime = normalize_runtime(argv[i])

            if runtime not in {"auto", "node", "php", "python"}:
                print_error("Invalid runtime. Use: auto, node, php, python")
                sys.exit(1)

            parsed.runtime = runtime
            i += 1
            continue

        if arg.startswith("--runtime="):
            runtime = normalize_runtime(arg.split("=", 1)[1])

            if runtime not in {"auto", "node", "php", "python"}:
                print_error("Invalid runtime. Use: auto, node, php, python")
                sys.exit(1)

            parsed.runtime = runtime
            i += 1
            continue

        if arg == "--cmd-mode":
            i += 1

            if i >= len(argv):
                print_error("Missing value for --cmd-mode. Use c or k.")
                sys.exit(1)

            mode = argv[i].lower()

            if mode not in {"c", "k"}:
                print_error("Invalid --cmd-mode. Use c or k.")
                sys.exit(1)

            parsed.cmd_mode = mode
            parsed.keep_open = mode == "k"
            i += 1
            continue

        if arg.startswith("--cmd-mode="):
            mode = arg.split("=", 1)[1].lower()

            if mode not in {"c", "k"}:
                print_error("Invalid --cmd-mode. Use c or k.")
                sys.exit(1)

            parsed.cmd_mode = mode
            parsed.keep_open = mode == "k"
            i += 1
            continue

        if arg == "--node":
            i += 1

            if i >= len(argv):
                print_error("Missing value for --node")
                sys.exit(1)

            parsed.node_bin = argv[i]
            i += 1
            continue

        if arg.startswith("--node="):
            parsed.node_bin = arg.split("=", 1)[1]
            i += 1
            continue

        if arg == "--php":
            i += 1

            if i >= len(argv):
                print_error("Missing value for --php")
                sys.exit(1)

            parsed.php_bin = argv[i]
            i += 1
            continue

        if arg.startswith("--php="):
            parsed.php_bin = arg.split("=", 1)[1]
            i += 1
            continue

        if arg == "--python":
            i += 1

            if i >= len(argv):
                print_error("Missing value for --python")
                sys.exit(1)

            parsed.python_bin = argv[i]
            i += 1
            continue

        if arg.startswith("--python="):
            parsed.python_bin = arg.split("=", 1)[1]
            i += 1
            continue

        if arg == "--cwd":
            i += 1

            if i >= len(argv):
                print_error("Missing value for --cwd")
                sys.exit(1)

            parsed.cwd = argv[i]
            i += 1
            continue

        if arg.startswith("--cwd="):
            parsed.cwd = arg.split("=", 1)[1]
            i += 1
            continue

        # First non-runner argument is the target file.
        parsed.target_file = arg
        parsed.forwarded_args = argv[i + 1 :]
        return parsed

    print_error("Missing target file.")
    print()
    print(_help_text())
    sys.exit(1)


def normalize_runtime(runtime: str) -> str:
    runtime = runtime.strip().lower()

    aliases = {
        "js": "node",
        "nodejs": "node",
        "py": "python",
        "python3": "python",
    }

    return aliases.get(runtime, runtime)


def resolve_path(value: str) -> str:
    return str(Path(value).expanduser().resolve())


def detect_runtime(target_file: str) -> str:
    ext = Path(target_file).suffix.lower()

    if ext in NODE_EXTENSIONS:
        return "node"

    if ext in PHP_EXTENSIONS:
        return "php"

    if ext in PYTHON_EXTENSIONS:
        return "python"

    print_error(f"Cannot auto-detect runtime from extension: {ext or '(no extension)'}")
    print("Use --runtime node, --runtime php, or --runtime python.")
    sys.exit(1)


def build_command(parsed: RunnerArgs, target_file: str) -> List[str]:
    runtime = parsed.runtime

    if runtime == "auto":
        runtime = detect_runtime(target_file)

    if runtime == "node":
        return [parsed.node_bin, target_file] + parsed.forwarded_args

    if runtime == "php":
        return [parsed.php_bin, target_file] + parsed.forwarded_args

    if runtime == "python":
        return [parsed.python_bin, target_file] + parsed.forwarded_args

    print_error(f"Unsupported runtime: {runtime}")
    sys.exit(1)


def command_to_string(cmd: List[str]) -> str:
    if os.name == "nt":
        return subprocess.list2cmdline(cmd)

    return shlex.join(cmd)


def run(parsed: RunnerArgs) -> int:
    if not parsed.target_file:
        print_error("Missing target file.")
        return 1

    target_file = resolve_path(parsed.target_file)

    if not os.path.isfile(target_file):
        print_error(f"Target file not found: {target_file}")
        return 1

    cwd = resolve_path(parsed.cwd) if parsed.cwd else os.getcwd()

    if not os.path.isdir(cwd):
        print_error(f"Working directory not found: {cwd}")
        return 1

    command = build_command(parsed, target_file)

    print("[RUNNER] CWD:", cwd)
    print("[RUNNER] CMD:", command_to_string(command))

    if parsed.dry_run:
        return 0

    if parsed.same_terminal:
        code = subprocess.call(command, cwd=cwd)

        if parsed.keep_open:
            input("\nPress Enter to exit...")

        return code

    if os.name == "nt":
        cmd_switch = f"/{parsed.cmd_mode}"

        subprocess.Popen(
            ["cmd", cmd_switch] + command,
            cwd=cwd,
            creationflags=subprocess.CREATE_NEW_CONSOLE,
        )

        return 0

    subprocess.Popen(command, cwd=cwd)
    return 0


def main() -> None:
    parsed = parse_args(sys.argv[1:])
    code = run(parsed)
    sys.exit(code)


if __name__ == "__main__":
    main()
