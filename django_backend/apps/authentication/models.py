from django.db import models
from django.contrib.auth.models import User
from decimal import Decimal


class UserBalance(models.Model):
    user = models.OneToOneField(User, on_delete=models.CASCADE, primary_key=True)
    saldo = models.DecimalField(
        max_digits=10, decimal_places=2, default=Decimal("0.00")
    )

    def __str__(self):
        # title admin page /admin/authentication/userbalance/<user_id>/change/
        return f"User Balance {self.user.username}"

    class Meta:
        db_table = "user_balance"
