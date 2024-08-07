import os
import sys
from django.core.paginator import EmptyPage, PageNotAnInteger, Paginator
from django.db.models import Case, IntegerField, Value, When


sys.path.append(
    os.path.abspath(os.path.join(os.path.dirname(__file__), "../../../../"))
)

from django.core.paginator import Paginator
from django.db.models import Q
from django.http import HttpRequest

from django_backend.apps.proxy.models import Proxy


def get_page_title(request: HttpRequest):
    filters = {
        "country": request.GET.get("country"),
        "city": request.GET.get("city"),
        "status": request.GET.get("status"),
        "timezone": request.GET.get("timezone"),
        "region": request.GET.get("region"),
        "type": request.GET.get("type"),
        "https": request.GET.get("https"),
    }
    search_query = request.GET.get("search")

    page_title = "Free Premium Proxy Lists"

    if filters["status"]:
        page_title = f"Proxy List Status {filters['status']}"
    if filters["country"]:
        page_title = f"{filters['country']} Proxy List"
    if filters["city"]:
        page_title = f"{filters['city']} Proxy List"
    if filters["timezone"]:
        page_title = f"Proxy List Timezone {filters['timezone']}"
    if filters["region"]:
        page_title = f"{filters['region']} Proxy List"
    if filters["type"]:
        page_title = f"{filters['type'].upper()} Proxy List"
    if filters["https"]:
        page_title = "HTTPS/SSL Proxy List"
    if search_query:
        page_title += f" (Search: {search_query})"

    return page_title


def get_proxy_list(request: HttpRequest, limit: int = 30):
    filters = {
        "country": request.GET.get("country"),
        "city": request.GET.get("city"),
        "status": request.GET.get("status"),
        "timezone": request.GET.get("timezone"),
        "region": request.GET.get("region"),
        "type": request.GET.get("type"),
        "https": request.GET.get("https"),
    }
    search_query = request.GET.get("search")

    # Build the query
    query = Q()
    if filters["status"]:
        query &= Q(status=filters["status"])
    if filters["country"]:
        query &= Q(country=filters["country"])
    if filters["city"]:
        query &= Q(city=filters["city"])
    if filters["timezone"]:
        query &= Q(timezone=filters["timezone"])
    if filters["region"]:
        query &= Q(region=filters["region"])
    if filters["type"]:
        query &= Q(type__icontains=filters["type"])
    if filters["https"]:
        query &= Q(https__icontains="true")
    if search_query:
        query &= Q(
            Q(proxy__icontains=search_query)
            | Q(region__icontains=search_query)
            | Q(city__icontains=search_query)
            | Q(country__icontains=search_query)
            | Q(timezone__icontains=search_query)
        )

    # Apply filters and annotations
    proxy_list = (
        Proxy.objects.filter(query)
        .annotate(
            is_active=Case(
                When(status="active", then=Value(1)),
                default=Value(0),
                output_field=IntegerField(),
            )
        )
        .order_by("-is_active", "-last_check")
    )

    # Paginate the results
    paginator = Paginator(proxy_list, limit)
    page_number = request.GET.get("page")
    pagination_title = None

    try:
        if page_number:
            pagination_title = f" Page {page_number}"
        proxy_list = paginator.page(page_number)
    except PageNotAnInteger:
        proxy_list = paginator.page(1)
    except EmptyPage:
        proxy_list = paginator.page(paginator.num_pages)

    return proxy_list, pagination_title
