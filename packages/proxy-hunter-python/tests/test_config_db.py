import pytest, sys
from dataclasses import dataclass
from proxy_hunter.utils.config import ConfigDB


def test_set_and_get_config(tmp_path):
    # use an on-disk temporary sqlite file to ensure persistence across connections
    db_file = tmp_path / "test_config.db"
    db = ConfigDB(driver="sqlite", db_path=str(db_file))

    # Create configs using the new `set` API
    db.set("features", ["login", "register", "api"])
    db.set("app_settings", {"theme": "dark", "version": 2.5})

    # Read them back
    features = db.get("features")
    app_settings = db.get("app_settings")

    assert isinstance(features, list)
    assert features == ["login", "register", "api"]

    assert isinstance(app_settings, dict)
    assert app_settings["theme"] == "dark"
    assert pytest.approx(app_settings["version"]) == 2.5

    db.close()


def test_update_and_delete_config(tmp_path):
    db_file = tmp_path / "test_config2.db"
    db = ConfigDB(driver="sqlite", db_path=str(db_file))

    db.set("key1", "value1")
    assert db.get("key1") == "value1"

    # update using set again
    db.set("key1", "value2")
    assert db.get("key1") == "value2"

    db.delete("key1")
    assert db.get("key1") is None

    db.close()


def test_dataclass_restore(tmp_path):
    db_file = tmp_path / "test_config_dataclass.db"
    db = ConfigDB(driver="sqlite", db_path=str(db_file))

    @dataclass
    class AppConfig:
        name: str
        version: float
        debug: bool

    cfg = AppConfig(name="MyApp", version=1.0, debug=True)
    db.set("app_info", cfg)

    restored: AppConfig = db.get("app_info", model=AppConfig)

    assert isinstance(restored, AppConfig)
    assert restored.name == "MyApp"
    assert pytest.approx(restored.version) == 1.0
    assert restored.debug is True

    db.close()


if __name__ == "__main__":
    sys.exit(pytest.main([__file__]))
