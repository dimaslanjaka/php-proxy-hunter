import os
import stat
from pathlib import Path

import pytest

from proxy_hunter.utils.file import delete_path


def test_delete_file(tmp_path: Path):
    f = tmp_path / "testfile.txt"
    f.write_text("hello")

    # ensure file exists
    assert f.exists()

    delete_path(str(f))

    assert not f.exists()


def test_delete_directory(tmp_path: Path):
    d = tmp_path / "subdir"
    d.mkdir()
    (d / "a.txt").write_text("a")
    (d / "b.txt").write_text("b")

    assert d.exists()

    delete_path(str(d))

    assert not d.exists()


def test_delete_readonly_file(tmp_path: Path):
    f = tmp_path / "readonly.txt"
    f.write_text("locked")

    # make read-only
    os.chmod(f, stat.S_IREAD)

    delete_path(str(f))

    assert not f.exists()


def test_delete_nonexistent_path_prints_message(capsys):
    path = "this_path_does_not_exist_12345"
    delete_path(path)
    captured = capsys.readouterr()
    assert "does not exist" in captured.out


if __name__ == "__main__":
    pytest.main(["-vvv", "-s", __file__])
