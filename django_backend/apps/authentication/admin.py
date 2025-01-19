from django.contrib import admin
from .models import UserFields


@admin.register(UserFields)
class UserFieldsAdmin(admin.ModelAdmin):
    list_display = ("user", "saldo", "phone")

    def __str__(self):
        return "User Balance"
