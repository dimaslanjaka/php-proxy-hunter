from django.core.cache import cache
from django.http import HttpRequest, JsonResponse
from django.utils import timezone
from datetime import timedelta


def clear_cache_view(request: HttpRequest):
    """
    Clear whole django caches
    """
    # Key to store the timestamp of the last cache clearing
    session_key = "cache_cleared_at"
    last_cleared_at = request.session.get(session_key)

    # Get the current time
    now = timezone.now()

    # Check if the session variable is not set or older than 1 hour
    if not last_cleared_at or (now - last_cleared_at) > timedelta(hours=1):
        cache.clear()
        # Update the session variable with the current time
        request.session[session_key] = now
        return JsonResponse({"status": "Cache cleared"})
    else:
        return JsonResponse({"status": "Cache clearing not required yet"})
