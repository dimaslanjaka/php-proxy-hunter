import ipaddress
from django.http import HttpRequest

# List of Cloudflare IP ranges
CLOUDFLARE_IP_RANGES = [
    "199.27.128.0/21",
    "173.245.48.0/20",
    "103.21.244.0/22",
    "103.22.200.0/22",
    "103.31.4.0/22",
    "141.101.64.0/18",
    "108.162.192.0/18",
    "190.93.240.0/20",
    "188.114.96.0/20",
    "197.234.240.0/22",
    "198.41.128.0/17",
    "162.158.0.0/15",
    "104.16.0.0/12",
]


class ServerUtils:

    @staticmethod
    def get_request_ip(request: HttpRequest) -> str:
        """
        Retrieve the IP address of the client making the request.

        If the request is coming through Cloudflare, return the IP from the
        'CF-Connecting-IP' header. Otherwise, return the client IP from other
        possible headers.

        Args:
            request (HttpRequest): The Django HTTP request object.

        Returns:
            str: The IP address of the client.
        """
        if ServerUtils.is_cloudflare(request):
            return request.headers.get("CF-Connecting-IP", "")
        else:
            return ServerUtils.get_client_ip(request)

    @staticmethod
    def is_cloudflare(request: HttpRequest) -> bool:
        """
        Determine if the request is coming through Cloudflare.

        This function checks if the request IP is within Cloudflare's IP range
        and if necessary Cloudflare headers are present in the request.

        Args:
            request (HttpRequest): The Django HTTP request object.

        Returns:
            bool: True if the request is from Cloudflare, False otherwise.
        """
        return ServerUtils._cloudflare_check_ip(
            request.META.get("REMOTE_ADDR", "")
        ) and ServerUtils._cloudflare_requests_check(request)

    @staticmethod
    def _cloudflare_check_ip(ip: str) -> bool:
        """
        Check if the given IP address is within Cloudflare's IP ranges.

        Args:
            ip (str): The IP address to check.

        Returns:
            bool: True if the IP is in Cloudflare's IP ranges, False otherwise.
        """
        if ip:
            try:
                ip_obj = ipaddress.ip_address(ip)
                for cidr in CLOUDFLARE_IP_RANGES:
                    if ip_obj in ipaddress.ip_network(cidr):
                        return True
            except ValueError:
                pass
        return False

    @staticmethod
    def _cloudflare_requests_check(request: HttpRequest) -> bool:
        """
        Check if the necessary Cloudflare headers are present in the request.

        Args:
            request (HttpRequest): The Django HTTP request object.

        Returns:
            bool: True if all required Cloudflare headers are present, False otherwise.
        """
        headers = ["CF-Connecting-IP", "CF-IPCountry", "CF-RAY", "CF-Visitor"]
        return all(header in request.headers for header in headers)

    @staticmethod
    def get_client_ip(request: HttpRequest) -> str:
        """
        Retrieve the client's IP address from various possible headers.

        Args:
            request (HttpRequest): The Django HTTP request object.

        Returns:
            str: The client's IP address or an empty string if not found.
        """
        ipaddress = (
            request.headers.get("CF-Connecting-IP")
            or request.META.get("HTTP_CLIENT_IP")
            or request.META.get("HTTP_X_FORWARDED_FOR")
            or request.META.get("HTTP_X_FORWARDED")
            or request.META.get("HTTP_FORWARDED_FOR")
            or request.META.get("HTTP_FORWARDED")
            or request.META.get("REMOTE_ADDR", "")
        )
        return ipaddress

    @staticmethod
    def user_agent(request: HttpRequest) -> str:
        """
        Retrieve the User-Agent string from the request headers.

        Args:
            request (HttpRequest): The Django HTTP request object.

        Returns:
            str: The User-Agent string or an empty string if not present.
        """
        return request.headers.get("User-Agent", "")
