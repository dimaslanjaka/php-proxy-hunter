from pathlib import Path
import subprocess
import certifi
import re
import warnings
import requests

from cryptography import x509
from cryptography.hazmat.primitives import hashes
from cryptography.utils import CryptographyDeprecationWarning

# ------------------------------------------------------------------------------
# Configuration
# ------------------------------------------------------------------------------

last_merged_certificates_path = Path("tmp/certificates/merged_certificates.pem")

download_links = [
    "https://curl.se/ca/cacert.pem",
    "https://raw.githubusercontent.com/bagder/ca-bundle/master/ca-bundle.crt",
]

# Using print statements instead of logging to keep output simple


# ------------------------------------------------------------------------------
# PEM Parsing Utilities
# ------------------------------------------------------------------------------

PEM_CERT_PATTERN = re.compile(
    rb"-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----",
    re.DOTALL,
)


def extract_certificates_from_pem(data: bytes) -> list[bytes]:
    """Extract individual PEM certificate blocks from raw bytes."""
    return PEM_CERT_PATTERN.findall(data)


def fingerprint_certificate(pem_block: bytes) -> bytes:
    """Return SHA256 fingerprint of a PEM certificate."""
    with warnings.catch_warnings():
        warnings.filterwarnings("ignore", category=CryptographyDeprecationWarning)
        cert = x509.load_pem_x509_certificate(pem_block)

        # Reject non-positive serial numbers
        serial = getattr(cert, "serial_number", 0)
        if serial <= 0:
            raise ValueError("Certificate has non-positive serial number")

    return cert.fingerprint(hashes.SHA256())


# ------------------------------------------------------------------------------
# Core Merge Logic
# ------------------------------------------------------------------------------


def merge_certificates(
    folders: list[Path],
    output_path: str | Path,
) -> Path:
    """
    Merge certifi bundle with certificates from multiple folders
    and external download sources.

    Deduplicates certificates using SHA256 fingerprint.
    """
    output = Path(output_path)
    certifi_bundle = Path(certifi.where())

    seen_fingerprints: set[bytes] = set()
    merged_cert_blocks: list[bytes] = []

    def add_certificate(pem_block: bytes):
        try:
            fp = fingerprint_certificate(pem_block)
            if fp not in seen_fingerprints:
                seen_fingerprints.add(fp)
                merged_cert_blocks.append(pem_block.strip())
        except Exception as e:
            print(f"Skipping certificate block: {e}")

    # --------------------------------------------------------------------------
    # 1️⃣ Load certifi bundle first
    # --------------------------------------------------------------------------
    for block in extract_certificates_from_pem(certifi_bundle.read_bytes()):
        add_certificate(block)

    # --------------------------------------------------------------------------
    # 2️⃣ Load certificates from provided folders
    # --------------------------------------------------------------------------
    for folder in folders:
        if not folder.exists() or not folder.is_dir():
            continue

        cert_files = list(folder.glob("*.pem")) + list(folder.glob("*.crt"))
        for cert_file in cert_files:
            data = cert_file.read_bytes()
            for block in extract_certificates_from_pem(data):
                add_certificate(block)

    # --------------------------------------------------------------------------
    # 3️⃣ Download and merge external CA bundles
    # --------------------------------------------------------------------------
    for url in download_links:
        try:
            response = requests.get(url, timeout=15, verify=certifi.where())
            response.raise_for_status()

            for block in extract_certificates_from_pem(response.content):
                add_certificate(block)

            print(f"Downloaded and merged: {url}")

        except Exception as e:
            print(f"Failed to download {url}: {e}")

    # --------------------------------------------------------------------------
    # 4️⃣ Write merged bundle
    # --------------------------------------------------------------------------
    if not merged_cert_blocks:
        raise ValueError("No valid certificates found.")

    output.parent.mkdir(parents=True, exist_ok=True)

    with output.open("wb") as outfile:
        for block in merged_cert_blocks:
            outfile.write(block)
            outfile.write(b"\n")

    print(f"Merged bundle created: {output}")
    print(f"Total unique certificates: {len(merged_cert_blocks)}")

    return output


# ------------------------------------------------------------------------------
# Initialization Logic
# ------------------------------------------------------------------------------


def initialize_default_bundle(candidate_folders: list[Path]) -> Path:
    """
    Create default merged bundle at last_merged_certificates_path.

    - Searches for certificates in candidate folders
    - Always includes certifi bundle
    - Includes external downloads
    - Avoids filename overwrite issues
    """
    last_merged_certificates_path.parent.mkdir(parents=True, exist_ok=True)

    valid_folders = [
        folder for folder in candidate_folders if folder.exists() and folder.is_dir()
    ]

    return merge_certificates(
        valid_folders,
        last_merged_certificates_path,
    )


download_folder = Path("tmp/download/certificates")
download_folder.mkdir(parents=True, exist_ok=True)


def create_certificate(domain):
    with open(f"{download_folder}/{domain}.pem", "w") as pem_file:
        pem_file.write(f"{domain} Global Root GC CA\n===============================\n")
        result = subprocess.run(
            ["openssl", "s_client", "-showcerts", "-connect", f"{domain}:443"],
            input="",
            capture_output=True,
            text=True,
        )
        result = subprocess.run(
            ["openssl", "x509", "-outform", "PEM"],
            input=result.stdout,
            capture_output=True,
            text=True,
        )
        pem_file.write(result.stdout)


# ------------------------------------------------------------------------------
# Auto Initialization
# ------------------------------------------------------------------------------

if not last_merged_certificates_path.exists() or __name__ == "__main__":
    # Create PEM files for each domain
    domains = [
        "www.google.com",
        "www.instagram.com",
        "www.facebook.com",
        "translate.google.co.id",
        "translate.google.com",
        "api.facebook.com",
        "graph.facebook.com",
        "graph.beta.facebook.com",
        "developers.facebook.com",
        "facebook.com",
        "bing.com",
        "webmanajemen.com",
        "dash.cloudflare.com",
        "www.cloudflare.com",
        "cloudflare.com",
        "api.myxl.xlaxiata.co.id",
        "otp.api.axis.co.id",
        "nq.api.axis.co.id",
        "axis.co.id",
        "aigo.api.axis.co.id",
        "api.axis.co.id",
        "m-assets.api.axis.co.id",
        "profile.api.axis.co.id",
        "go.axis.co.id",
        "click.axis.co.id",
        "trxpayments.api.axis.co.id",
        "trxpackages.api.axis.co.id",
        "products.api.axis.co.id",
        "packages.api.axis.co.id",
        "order.api.axis.co.id",
        "games.axis.co.id",
        "httpbin.org",
        "www.httpbin.org",
        "yahoo.com",
    ]
    unique_domains = list(set(domains))

    for domain in unique_domains:
        create_certificate(domain)

    initialize_default_bundle([Path("certificates"), Path("data"), download_folder])
