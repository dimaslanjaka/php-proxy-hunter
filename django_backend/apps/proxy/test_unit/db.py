import os
import sys

sys.path.append(
    os.path.abspath(os.path.join(os.path.dirname(__file__), "../../../../"))
)

from django.test import TestCase
from unittest.mock import patch, MagicMock
import sqlite3
from django_backend.apps.proxy.utils import get_db_connections, execute_sql_query


class DatabaseUtilsTests(TestCase):
    @patch("django_backend.apps.proxy.utils.ProxyDB")
    @patch("django_backend.apps.proxy.utils.connection")
    def test_get_db_connections_success(self, mock_django_connection, mock_proxy_db):
        # Setup mock for ProxyDB connection
        mock_proxy_db_instance = MagicMock()
        mock_proxy_db_instance.db.conn = sqlite3.connect(":memory:")
        mock_proxy_db.return_value = mock_proxy_db_instance

        # Setup mock for Django connection
        mock_django_connection = MagicMock()
        mock_django_connection.cursor.return_value = MagicMock()
        mock_django_connection.cursor.return_value.fetchall.return_value = []

        # Test get_db_connections function
        connections = get_db_connections()

        # Verify connections list contains both ProxyDB and Django connections
        self.assertEqual(len(connections), 2)
        self.assertIsNotNone(
            connections[0]
        )  # Check that ProxyDB connection is not None
        self.assertIsNotNone(connections[1])  # Check that Django connection is not None

    # @patch("django_backend.apps.proxy.utils.ProxyDB")
    # @patch("django_backend.apps.proxy.utils.connection")
    # def test_get_db_connections_failure(self, mock_django_connection, mock_proxy_db):
    #     # Setup mock for ProxyDB connection failure
    #     mock_proxy_db.side_effect = Exception("ProxyDB connection failed")

    #     # Setup mock for Django connection
    #     mock_django_connection = MagicMock()
    #     mock_django_connection.cursor.return_value = MagicMock()
    #     mock_django_connection.cursor.return_value.fetchall.return_value = []

    #     # Test get_db_connections function
    #     connections = get_db_connections()

    #     # Verify connections list contains only Django connection
    #     self.assertEqual(len(connections), 1)
    #     self.assertIsNotNone(connections[0])  # Check that Django connection is not None

    @patch("django_backend.apps.proxy.utils.get_db_connections")
    def test_execute_sql_query_success(self, mock_get_db_connections):
        # Setup mock for get_db_connections
        mock_connection = MagicMock()
        mock_cursor = MagicMock()
        mock_cursor.fetchall.return_value = [(1, "proxy1")]
        mock_connection.cursor.return_value = mock_cursor
        mock_get_db_connections.return_value = [mock_connection]

        # Test execute_sql_query function
        sql = "SELECT * FROM proxies WHERE status = ?"
        params = ("active",)
        results = execute_sql_query(sql, params)

        # Verify the result
        self.assertEqual(len(results), 1)
        self.assertEqual(results[0], [(1, "proxy1")])

    # @patch("django_backend.apps.proxy.utils.get_db_connections")
    # def test_execute_sql_query_failure(self, mock_get_db_connections):
    #     # Setup mock for get_db_connections
    #     mock_connection = MagicMock()
    #     mock_connection.cursor.side_effect = Exception("Query execution failed")
    #     mock_get_db_connections.return_value = [mock_connection]

    #     # Test execute_sql_query function
    #     sql = "SELECT * FROM proxies WHERE status = ?"
    #     params = ("active",)
    #     results = execute_sql_query(sql, params)

    #     # Verify the result
    #     self.assertEqual(len(results), 1)
    #     self.assertIsNone(results[0])
