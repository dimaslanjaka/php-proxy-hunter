import os
import sys
sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), '../')))
sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), '../../../../')))
from .admin import *

# create user by CLI
# python manage.py create_user demo@gmail.com demo pass


class TestUser(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.admin = TestAdmin()
        # print(f"login admin {cls.admin.admin_email}")
        cls.admin.test_1_visit_homepage()
        cls.admin.test_2_login()
        cls.headers = cls.admin.headers
        cls.login_data = {
            'username': 'demo',
            'password': 'pass',
            'email': 'no-reply@blogger.com'
        }

    def test_1_create(self):
        url = 'http://127.0.0.1:8000/auth/create'
        self.headers.update({
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRFToken': utils.parse_csrf_token_from_cookie_file(self.admin.cookie_file)
        })
        response = build_request(endpoint=url, headers=self.headers, post_data=json.dumps(self.login_data), method='POST', cookie_file=self.admin.cookie_file)
        if response.status_code != 201 and response.status_code != 304:
            print('test_1_create', response.text, response.status_code)
        self.assertTrue(response.status_code == 201 or response.status_code == 304)
        self.assertTrue('created success' in response.text)

    def test_2_login(self):
        self.admin.test_4_logout()
        url = 'http://127.0.0.1:8000/auth/login'
        response = build_request(endpoint=url, headers=self.headers, post_data=json.dumps(self.login_data), method='POST', cookie_file=self.admin.cookie_file)
        if response.status_code != 200:
            print('test_2_login', response.text)
        self.assertEqual(response.status_code, 200)
        self.assertTrue("Login success" in response.text)

    def test_4_logout(self):
        url = 'http://127.0.0.1:8000/auth/logout'
        response = build_request(endpoint=url, cookie_file=self.admin.cookie_file, headers=self.headers)
        self.assertFalse('HTTP 401 Unauthorized' in response.text)
        self.assertTrue('Logout success' in response.text)

    def test_5_delete(self):
        self.admin.test_2_login()
        url = f"http://127.0.0.1:8000/auth/delete/{self.login_data['username']}"
        response = build_request(endpoint=url, cookie_file=self.admin.cookie_file, headers=self.headers)
        self.assertTrue('deleted success' in response.text)
