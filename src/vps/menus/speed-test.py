import os
import sys

# Add project root to sys.path
sys.path.insert(
    0, os.path.abspath(os.path.join(os.path.dirname(__file__), "../../../"))
)

from src.vps.vps_connector import VPSConnector
from src.vps.sftp_speed_test import run_speed_test


def speed_test(vps: VPSConnector):
    """Menu wrapper that calls the CLI-style `run_speed_test`.

    Note: `run_speed_test` reads `load_sftp_config()` and creates its own
    SSH/SFTP connection; this wrapper ignores the provided `vps` instance.
    """
    # Default: 10 MiB
    return run_speed_test(size_mb=10, remote_file=None)


def register():
    return {"label": "Run SFTP Speed Test (upload+download)", "action": speed_test}
