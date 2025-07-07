import os
import sys

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "../..")))

import pytest

from src.vps.vps_connector import VPSConnector, load_sftp_config
from src.func import get_relative_path


@pytest.fixture
def sftp_config():
    # Use a test config or mock config for real tests
    config_path = get_relative_path(".vscode/sftp.json")
    return load_sftp_config(config_path)


@pytest.fixture
def vps(sftp_config, tmp_path):
    # Use a temp local path for isolation
    connector = VPSConnector(
        host=sftp_config["host"],
        port=sftp_config["port"],
        username=sftp_config["username"],
        password=sftp_config["password"],
        key_path=sftp_config["key_path"],
        remote_path=sftp_config["remote_path"],
        local_path=str(tmp_path),
    )
    yield connector
    connector.close()


def test_constructor_sets_properties(vps, sftp_config, tmp_path):
    assert vps.host == sftp_config["host"]
    assert vps.port == sftp_config["port"]
    assert vps.username == sftp_config["username"]
    assert vps.remote_path == sftp_config["remote_path"]
    assert vps.local_path == str(tmp_path)


def test_delete_local_file(vps, tmp_path):
    test_file = tmp_path / "delete_me.txt"
    test_file.write_text("delete me")
    assert test_file.exists()
    vps.delete_local(str(test_file))
    assert not test_file.exists()


def test_delete_local_folder(vps, tmp_path):
    test_folder = tmp_path / "folder"
    test_folder.mkdir()
    (test_folder / "file1.txt").write_text("1")
    (test_folder / "file2.txt").write_text("2")
    assert test_folder.exists()
    vps._delete_local_folder(str(test_folder))
    assert not test_folder.exists()


if __name__ == "__main__":
    sys.exit(
        pytest.main(
            [
                "-ra",
                "-v",
                __file__,
            ]
        )
    )
