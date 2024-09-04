import subprocess
import os

# Get the directory of the currently running script
script_directory = os.path.dirname(os.path.abspath(__file__))

# Install package
subprocess.run(["python", "setup.py", "develop"], cwd=script_directory, check=True)
# Run test
subprocess.run(["python", "-m", "pytest", "-vvv"], cwd=script_directory, check=True)
