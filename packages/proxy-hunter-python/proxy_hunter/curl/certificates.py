from pathlib import Path
import certifi
import re
import warnings
import logging
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

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)


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
            logger.debug("Skipping certificate block: %s", e)

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

            logger.info("Downloaded and merged: %s", url)

        except Exception as e:
            logger.warning("Failed to download %s: %s", url, e)

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

    logger.info("Merged bundle created: %s", output)
    logger.info("Total unique certificates: %d", len(merged_cert_blocks))

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


# ------------------------------------------------------------------------------
# Auto Initialization
# ------------------------------------------------------------------------------

if not last_merged_certificates_path.exists() or __name__ == "__main__":
    initialize_default_bundle([Path("certificates"), Path("data")])
