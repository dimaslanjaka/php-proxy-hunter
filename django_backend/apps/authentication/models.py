from django.db import models
from django.contrib.auth.models import User


class UserBalance(models.Model):
    user = models.OneToOneField(User, on_delete=models.CASCADE, primary_key=True)
    saldo = models.DecimalField(max_digits=10, decimal_places=2, default=0.00)

    class Meta:
        db_table = 'user_balance'
