from django import template
from django.utils.safestring import mark_safe

register = template.Library()


@register.filter
def proxy_label(row):
    if not row:
        return "-"

    result = []
    if row.status == "active":
        if row.https == "true":
            badge = '<a href="/proxy?https=true" class="bg-green-100 text-green-800 text-xs font-medium me-2 px-2.5 py-0.5 rounded border border-green-400 mb-1">SSL</a>'
            result.append(badge)
        if row.type:
            proxy_types = set(row.type.split("-"))

            for s in proxy_types:
                badge = None
                if s.lower() == "http":
                    badge = '<a href="/proxy?type=http" class="bg-blue-100 text-blue-800 text-xs font-medium me-2 px-2.5 py-0.5 rounded border border-blue-400 mb-1">HTTP</a>'
                elif s.lower() == "socks4":
                    badge = '<a href="/proxy?type=socks4" class="bg-green-100 text-blue-800 text-xs font-medium me-2 px-2.5 py-0.5 rounded border border-green-400 mb-1">SOCKS4</a>'
                elif s.lower() == "socks5":
                    badge = '<a href="/proxy?type=socks5" class="bg-red-100 text-blue-800 text-xs font-medium me-2 px-2.5 py-0.5 rounded border border-red-400 mb-1">SOCKS5</a>'
                if badge:
                    result.append(badge)
    else:
        badge = '<span class="bg-red-100 text-red-800 text-xs font-medium me-2 px-2.5 py-0.5 rounded border border-red-400 mb-1">DEAD</span>'
        result.append(badge)

    badges_html = "".join(result)
    return mark_safe(f'<div class="flex flex-wrap space-x-1">{badges_html}</div>')
