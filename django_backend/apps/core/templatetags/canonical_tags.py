from urllib.parse import urlunparse
from django import template
from django.conf import settings
from django.http import HttpRequest
from django.urls import reverse
from django.utils.html import format_html

register = template.Library()


@register.simple_tag(takes_context=True)
def canonical_url(context):
    request = context.get("request")
    if request:
        # Build the canonical URL
        return format_html('<link rel="canonical" href="{}" />', get_full_url(request))
    return ""


def get_full_url(request: HttpRequest) -> str:
    # Get scheme and host from the request
    scheme = request.scheme
    host = request.get_host()

    # Determine if port should be included
    host_parts = host.split(":")
    if len(host_parts) == 2:
        # Host already includes port
        host_with_port = f"{host_parts[0]}:{host_parts[1]}"
    else:
        # Host does not include port
        if scheme == "https" and host in settings.ALLOWED_HOSTS:
            prod_port = int(settings.PRODUCTION_PORT)
            if prod_port not in (80, 443) and prod_port > 443:
                host_with_port = f"{host}:{prod_port}"
            else:
                host_with_port = host
        else:
            host_with_port = host

    # Get the full path including query parameters
    path = request.get_full_path()

    # Construct the full URL
    result = urlunparse((scheme, host_with_port, path, "", "", ""))
    # print(f"sitemap {result}")
    return result
