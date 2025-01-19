import environ
from django.contrib.auth import get_user_model
from django.core.management.base import BaseCommand


class Command(BaseCommand):
    help = "Create or update a superuser with credentials from the .env file\n\npython manage.py create_superuser"

    def handle(self, *args, **kwargs):
        # Initialize environment variables
        env = environ.Env()
        environ.Env.read_env()

        # Retrieve environment variables (/.env)
        username = env("DJANGO_SUPERUSER_USERNAME")
        password = env("DJANGO_SUPERUSER_PASSWORD")
        email = env("DJANGO_SUPERUSER_EMAIL")

        UserModel = get_user_model()

        # Check if the user already exists
        if UserModel.objects.filter(username=username).exists():
            user = UserModel.objects.get(username=username)
            user.email = email
            user.set_password(password)
            user.save()
            self.stdout.write(
                self.style.SUCCESS(f'Successfully updated superuser "{username}"')
            )
        else:
            UserModel.objects.create_superuser(
                username=username, password=password, email=email
            )
            self.stdout.write(
                self.style.SUCCESS(f'Successfully created superuser "{username}"')
            )
