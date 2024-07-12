import os
from django.core.asgi import get_asgi_application
from channels.routing import ProtocolTypeRouter

os.environ.setdefault('DJANGO_SETTINGS_MODULE', 'django_backend.settings')

application = ProtocolTypeRouter({
    "http": get_asgi_application(),
    # (http->django views is added by default)
})
