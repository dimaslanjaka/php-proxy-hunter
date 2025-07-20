import pytest
import sys
from proxy_hunter.utils.dict_helper import dict_updater


def test_dict_updater_case_insensitive_update():
    headers = {"Authorization": "old", "Content-Type": "application/json"}
    updates = {"authorization": "new", "content-type": "text/plain"}
    dict_updater(headers, updates)
    assert headers["Authorization"] == "new"
    assert headers["Content-Type"] == "text/plain"


def test_dict_updater_add_new_key():
    headers = {"Authorization": "old"}
    updates = {"X-IMI-TOKENID": "12345"}
    dict_updater(headers, updates)
    assert headers["X-IMI-TOKENID"] == "12345"


def test_dict_updater_mixed_case():
    headers = {"authorization": "old"}
    updates = {"AUTHORIZATION": "new"}
    dict_updater(headers, updates)
    assert headers["authorization"] == "new"


def test_dict_updater_multiple_keys():
    headers = {"A": "1", "b": "2"}
    updates = {"a": "10", "B": "20", "C": "30"}
    dict_updater(headers, updates)
    assert headers["A"] == "10"
    assert headers["b"] == "20"
    assert headers["C"] == "30"


if __name__ == "__main__":
    sys.exit(pytest.main([__file__]))
