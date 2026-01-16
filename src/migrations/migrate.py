import glob
import importlib.util
import os
import sys

# Ensure project root and `src` folder are on sys.path so migration modules
# can import project modules as plain module names (e.g. `ProxyDB`).
sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))
sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), "../../")))

from src.shared import init_db


def load_migrations():
    """Dynamically load migration scripts from the migrations directory."""
    migration_scripts = []
    # name of this file so we avoid loading it as a migration
    current_filename = os.path.splitext(os.path.basename(__file__))[0]
    for file_path in glob.glob(os.path.join(os.path.dirname(__file__), "*.py")):
        module_name = os.path.splitext(os.path.basename(file_path))[0]
        # skip package init and this loader script
        if module_name == "__init__" or module_name == current_filename:
            continue
        spec = importlib.util.spec_from_file_location(module_name, file_path)
        if spec is not None and spec.loader is not None:
            module = importlib.util.module_from_spec(spec)
            spec.loader.exec_module(module)
            # return both the loaded module and the source file path so callers
            # can report where the migration came from.
            migration_scripts.append((module, file_path))
    # sort by the module's MIGRATION_NUMBER (first element of tuple)
    return sorted(migration_scripts, key=lambda x: x[0].MIGRATION_NUMBER)


if __name__ == "__main__":
    migrations = load_migrations()
    db = init_db("mysql", False)
    for module, path in migrations:
        print(f"Applying migration: {module.MIGRATION_NUMBER} ({path})")
        module.migrate(db.db)
    print("All migrations applied.")
