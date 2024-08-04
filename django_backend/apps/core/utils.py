import json
import locale
import os
import random
import string
import sys
from typing import Any, Optional

from django.http import HttpRequest, JsonResponse

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "../../../")))

DEFAULT_CHAR_STRING = string.ascii_lowercase + string.digits


def rupiah_format(angka, with_prefix=False, desimal=2):
    locale.setlocale(locale.LC_NUMERIC, "IND")
    rupiah = locale.format("%.*f", (desimal, angka), True)
    if with_prefix:
        return "Rp. {}".format(rupiah)
    return rupiah


def generate_random_string(chars=DEFAULT_CHAR_STRING, size=6):
    return "".join(random.choice(chars) for _ in range(size))


def get_query_or_post_body(
    request: HttpRequest, key: str, default_value: Optional[Any] = None
):
    if request.method == "GET":
        # Get the proxy parameter from the query string
        return request.GET.get(key, default_value)

    elif request.method == "POST":
        content_type = request.content_type

        if content_type == "application/json":
            try:
                # Parse JSON data from the request body
                data = json.loads(request.body)
                return data.get(key, default_value)
            except json.JSONDecodeError:
                # return JsonResponse({"error": "Invalid JSON"}, status=400)
                pass

        elif content_type == "application/x-www-form-urlencoded":
            # Parse form data
            return request.POST.get(key, default_value)
    return default_value
