# Generated by Django 5.0 on 2024-08-24 07:28

from django.db import migrations, models


class Migration(migrations.Migration):

    initial = True

    dependencies = []

    operations = [
        migrations.CreateModel(
            name="ProcessStatus",
            fields=[
                (
                    "id",
                    models.BigAutoField(
                        auto_created=True,
                        primary_key=True,
                        serialize=False,
                        verbose_name="ID",
                    ),
                ),
                ("process_name", models.CharField(max_length=255, unique=True)),
                ("is_done", models.BooleanField(default=False)),
                ("timestamp", models.DateTimeField(auto_now=True)),
            ],
        ),
    ]
