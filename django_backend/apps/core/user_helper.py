from django.contrib.auth.models import User

# Example function to create a user


def create_user(username, email, password):
    user = User.objects.create_user(username=username, email=email, password=password)
    # Optionally set other attributes like first_name, last_name, etc.
    # user.first_name = "First"
    # user.last_name = "Last"
    user.save()
    return user
