from django.apps import AppConfig


class ProxyConfig(AppConfig):
    default_auto_field = 'django.db.models.BigAutoField'
    name = 'django_backend.apps.proxy'
    verbose_name = 'Proxy'

    def ready(self):
        # pylint: disable=unused-argument
        import django_backend.apps.proxy.signals  # unused: auto enable WAL journal mode


default_app_config = 'django_backend.apps.proxy.ProxyConfig'


# python manage.py makemigrations proxy --empty
# python manage.py makemigrations proxy
# python manage.py migrate proxy
