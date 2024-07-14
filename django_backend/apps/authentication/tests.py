import os
from django.test import TestCase
import json
import environ
from src.func_proxy import build_request
from src.func import get_relative_path
from . import utils

# create superuser
# python manage.py create_superuser


class AdminLoginTests(TestCase):
    def setUp(self):
        # Initialize django-environ
        env = environ.Env()

        # Fetch admin credentials from environment variables using django-environ
        self.admin_username = env('DJANGO_SUPERUSER_USERNAME', default='admin')
        self.admin_password = env('DJANGO_SUPERUSER_PASSWORD', default='adminpassword')
        self.admin_email = env('DJANGO_SUPERUSER_EMAIL', default='admin@example.com')

        self.cookie_file = get_relative_path('tmp/cookies/django-auth-test.txt')
        self.headers = {}
        self.csrf_token = None
        if os.path.exists(self.cookie_file):
            self.csrf_token = utils.parse_csrf_token_from_cookie_file(self.cookie_file)
            print(f'CSRF token {self.csrf_token}')
            self.headers.update({'X-CSRFToken': self.csrf_token})

    def test_admin_login(self):
        # Ensure login page URL (adjust if using a different URL pattern)
        url = 'http://127.0.0.1:8000/auth/login'

        # Simulate a POST request to login as admin
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

    def test_status(self):
        url = 'http://127.0.0.1:8000/auth/status'
        response = build_request(endpoint=url, cookie_file=self.cookie_file, headers=self.headers)
        json_response = response.json()
        self.assertTrue('username' in json_response and 'email' in json_response)
