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
            badge = (
                '<span class="bg-green-100 text-green-800 text-xs font-medium me-2 px-2.5 py-0.5 rounded border border-green-400 mb-1">'
                "HTTPS</span>"
            )
            result.append(badge)
        if row.type:
            proxy_types = row.type.split("-")

            for s in proxy_types:
                if s == "http":
                    badge = (
                        '<span class="bg-blue-100 text-blue-800 text-xs font-medium me-2 px-2.5 py-0.5 rounded border border-blue-400 mb-1">'
                        "HTTP</span>"
                    )
                elif s == "socks4":
                    badge = (
                        '<span class="bg-green-100 text-blue-800 text-xs font-medium me-2 px-2.5 py-0.5 rounded border border-green-400 mb-1">'
                        "SOCKS4</span>"
                    )
                elif s == "socks5":
                    badge = (
                        '<span class="bg-red-100 text-blue-800 text-xs font-medium me-2 px-2.5 py-0.5 rounded border border-red-400 mb-1">'
                        "SOCKS5</span>"
                    )
                result.append(badge)
    else:
        badge = (
            '<span class="bg-red-100 text-red-800 text-xs font-medium me-2 px-2.5 py-0.5 rounded border border-red-400 mb-1">'
            "DEAD</span>"
        )
        result.append(badge)

    badges_html = "".join(result)
    return mark_safe(f'<div class="flex flex-wrap space-x-1">{badges_html}</div>')
