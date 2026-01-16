import maxminddb
from typing import Optional, Dict
import os

PROJECT_ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
MMDB_PATH = os.path.join(PROJECT_ROOT, "src/GeoLite2-ASN.mmdb")


class ASNLookup:
    def __init__(self, mmdb_path: str = MMDB_PATH):
        self.reader = maxminddb.open_database(mmdb_path)

    def lookup(self, ip: str) -> Optional[Dict]:
        try:
            data = self.reader.get(ip)
            if not data:
                return None
            if not isinstance(data, dict):
                return None

            return {
                "asn": data.get("autonomous_system_number"),
                "org": data.get("autonomous_system_organization"),
                "network": data.get("network"),
            }
        except Exception:
            return None

    def classify(self, ip: str) -> str:
        info = self.lookup(ip)
        if not info or not info["org"]:
            return "unknown"

        org = str(info["org"]).lower()

        # Datacenter / Hosting keywords
        dc_keywords = [
            "amazon",
            "google",
            "microsoft",
            "digitalocean",
            "ovh",
            "hetzner",
            "linode",
            "alibaba",
            "oracle",
            "vultr",
            "leaseweb",
            "cloud",
            "hosting",
            "server",
        ]

        mobile_keywords = [
            "mobile",
            "cellular",
            "lte",
            "telkomsel",
            "verizon",
            "vodafone",
            "orange",
            "att",
            "t-mobile",
            "telekom",
        ]

        for k in dc_keywords:
            if k in org:
                return "datacenter"

        for k in mobile_keywords:
            if k in org:
                return "mobile"

        # Default: ISP = residential
        return "residential"

    def close(self):
        self.reader.close()
