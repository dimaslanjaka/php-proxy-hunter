from django.contrib.auth import get_user_model
from django.core.management.base import BaseCommand, CommandError

User = get_user_model()


class Command(BaseCommand):
    help = "Creates a new user\npython manage.py create_user demo@gmail.com demo pass"

    def add_arguments(self, parser):
        parser.add_argument("email", type=str, help="Email address of the user")
        parser.add_argument("username", type=str, help="Username of the user")
        parser.add_argument("password", type=str, help="Password of the user")

    def handle(self, *args, **options):
        email = (
            options.get("email") or "default@example.com"
        )  # Default email if not provided
        username = options["username"]
        password = options["password"]
        print(
            f"Creating user with username: {username}, password: {password}, email: {email}"
        )

        try:
            if User.objects.filter(username=username).exists():
                # Create user
                user = User.objects.get(username=username)
                user.email = email
                user.set_password(password)
                user.save()
                self.stdout.write(
                    self.style.SUCCESS(f'Successfully updated user "{username}"')
                )
            else:
                # Change user password
                user = User.objects.create_user(
                    email=email, username=username, password=password
                )
                user.set_password(password)
                user.save()
                self.stdout.write(
                    self.style.SUCCESS(f"Successfully created user: {username}")
                )
        except Exception as e:
            raise CommandError(f"Failed to create user: {username}. Error: {str(e)}")
