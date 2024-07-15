import os
import sys
sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), '../')))
sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), '../../../../')))
import json
import unittest

import environ

from src.func import get_relative_path
from src.func_proxy import build_request

from django_backend.apps.authentication import utils

# create superuser
# modify `.env` file
# DJANGO_SUPERUSER_USERNAME, DJANGO_SUPERUSER_PASSWORD, DJANGO_SUPERUSER_EMAIL
# python manage.py create_superuser


class TestAdmin(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        # Initialize django-environ
        env = environ.Env()

        # Fetch admin credentials from environment variables using django-environ
        cls.admin_username = env('DJANGO_SUPERUSER_USERNAME', default='admin')
        cls.admin_password = env('DJANGO_SUPERUSER_PASSWORD', default='adminpassword')
        cls.admin_email = env('DJANGO_SUPERUSER_EMAIL', default='admin@example.com')

        cls.cookie_file = get_relative_path('tmp/cookies/django-auth-test.txt')
        cls.headers = {}
        cls.csrf_token = None
        if os.path.exists(cls.cookie_file):
            cls.csrf_token = utils.parse_csrf_token_from_cookie_file(cls.cookie_file)
            cls.headers.update({'X-CSRFToken': cls.csrf_token})

    def test_1_visit_homepage(self):
        url = 'http://127.0.0.1:8000'
        response = build_request(endpoint=url)
        self.assertTrue(response.status_code == 200)

    def test_2_login(self):
        url = 'http://127.0.0.1:8000/auth/login'
        login_data = {
            'username': self.admin_username,
            'password': self.admin_password
        }
        self.headers.update({
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        })
        response = build_request(endpoint=url, post_data=json.dumps(login_data), method='POST', cookie_file=self.cookie_file, headers=self.headers)
        if response.status_code != 200:
            print(response.text)

        self.assertEqual(response.status_code, 200)
        self.assertTrue("Welcome, admin" in response.text or "Login success" in response.text)

    def test_3_status(self):
        url = 'http://127.0.0.1:8000/auth/status'
        response = build_request(endpoint=url, cookie_file=self.cookie_file, headers=self.headers)
        self.assertTrue('username' in response.text and 'email' in response.text)

    def test_4_logout(self):
        url = 'http://127.0.0.1:8000/auth/logout'
        response = build_request(endpoint=url, cookie_file=self.cookie_file, headers=self.headers)
        # print('HTTP 401 Unauthorized' in response.text)
        self.assertTrue('Logout success' in response.text)


if __name__ == '__main__':
    unittest.main()
