from django.core.management.base import BaseCommand
from django.contrib.auth.hashers import make_password


class Command(BaseCommand):
    help = "Generate a hashed password from a raw password\n\npython manage.py hash_password <raw_password>"

    def add_arguments(self, parser):
        parser.add_argument("password", type=str, help="Raw password to be hashed")

    def handle(self, *args, **kwargs):
        raw_password = kwargs["password"]

        # Hash the password
        hashed_password = make_password(raw_password)

        # Output the hashed password
        self.stdout.write(self.style.SUCCESS(f"Hashed password: \n\n{hashed_password}"))
