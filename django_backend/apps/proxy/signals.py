from django.db.backends.signals import connection_created
from django.dispatch import receiver
from django.db import connection, transaction


@transaction.atomic
def activate_wal_mode2():
    with connection.cursor() as cursor:
        cursor.execute("PRAGMA journal_mode = WAL;")


@receiver(connection_created)
def activate_wal_mode(sender, connection, **kwargs):
    # print('activating journal mode')
    if connection.vendor == 'sqlite':
        with connection.cursor() as cursor:
            cursor.execute('PRAGMA journal_mode = WAL;')
