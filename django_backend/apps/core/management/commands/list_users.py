import os
import sys

BASE_DIR = os.path.abspath(os.path.join(os.path.dirname(__file__), "../../../../../"))
SRC_DIR = os.path.join(BASE_DIR, "src")
sys.path.append(SRC_DIR)

from django.contrib.auth import get_user_model
from django.core.management.base import BaseCommand

from django_backend.apps.authentication.models import UserBalance
from django_backend.apps.core.utils import rupiah_format

# list users with custom fields
# python manage.py list_users


class Command(BaseCommand):
    help = "List users with their balance"

    def handle(self, *args, **kwargs):
        UserModel = get_user_model()
        users = UserModel.objects.all()
        self.stdout.write("=" * 50 + "\n")
        self.stdout.write("username<email>\t\t\t\tBalance")
        self.stdout.write("=" * 50 + "\n")

        for user in users:
            info = f"{user.username}<{user.email}>"
            try:
                user_balance = UserBalance.objects.get(user=user)
                self.stdout.write(f"{info}\tRp. {rupiah_format(user_balance.saldo)}")
            except UserBalance.DoesNotExist:
                self.stdout.write(f"{info}\tRp. 0,00")
