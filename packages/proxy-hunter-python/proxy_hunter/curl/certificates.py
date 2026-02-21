from pathlib import Path
import certifi
import re
import warnings
import logging

from cryptography import x509
from cryptography.hazmat.primitives import hashes
from cryptography.utils import CryptographyDeprecationWarning

# Default location used by your curl layer
last_merged_certificates_path = Path("tmp/certificates/merged_certificates.pem")

# Regex to extract individual PEM certificate blocks
PEM_CERT_PATTERN = re.compile(
    rb"-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----",
    re.DOTALL,
)


def extract_certificates_from_pem(data: bytes) -> list[bytes]:
    """Extract individual PEM certificate blocks from raw bytes."""
    return PEM_CERT_PATTERN.findall(data)


def fingerprint_certificate(pem_block: bytes) -> bytes:
    """Return SHA256 fingerprint of a PEM certificate."""
    try:
        # Suppress the specific Cryptography deprecation warning while loading
        # so we can gracefully handle certificates that will become invalid
        # in future cryptography releases. We still validate the serial
        # number after loading and raise on non-positive values. Keep the
        # serial-number access inside the suppressed warnings context because
        # cryptography may emit the deprecation when parsing the serial.
        with warnings.catch_warnings():
            warnings.filterwarnings("ignore", category=CryptographyDeprecationWarning)
            cert = x509.load_pem_x509_certificate(pem_block)
            serial = getattr(cert, "serial_number", 0)
            if serial <= 0:
                raise ValueError("Certificate has non-positive serial number")
    except Exception:
        raise
    return cert.fingerprint(hashes.SHA256())


def merge_certificates(folder_path: str | Path, output_path: str | Path) -> Path:
    """
    Merge certifi CA bundle with all .pem and .crt files
    found inside `folder_path`.

    Deduplicates certificates using SHA256 fingerprint.

    Returns the output bundle path.
    """
    folder = Path(folder_path)
    output = Path(output_path)

    if not folder.exists() or not folder.is_dir():
        raise ValueError(f"Invalid folder path: {folder}")

    certifi_bundle = Path(certifi.where())
    cert_files = list(folder.glob("*.pem")) + list(folder.glob("*.crt"))

    seen_fingerprints: set[bytes] = set()
    merged_cert_blocks: list[bytes] = []

    def add_certificate(pem_block: bytes):
        try:
            fp = fingerprint_certificate(pem_block)
            if fp not in seen_fingerprints:
                seen_fingerprints.add(fp)
                merged_cert_blocks.append(pem_block.strip())
        except Exception as e:
            # Ignore invalid/corrupted cert blocks or certs with
            # non-positive serial numbers. Log at debug level so
            # callers can enable visibility if desired.
            logger = logging.getLogger(__name__)
            logger.debug("Skipping certificate block: %s", e)

    # 1️⃣ Load certifi bundle certificates first
    for block in extract_certificates_from_pem(certifi_bundle.read_bytes()):
        add_certificate(block)

    # 2️⃣ Load custom certificates
    for cert_file in cert_files:
        data = cert_file.read_bytes()
        for block in extract_certificates_from_pem(data):
            add_certificate(block)

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


def initialize_default_bundle(candidate_folders: list[Path]) -> Path:
    """
    Create default merged bundle at last_merged_certificates_path.

    Searches for custom certs in candidate folders.
    Falls back to certifi-only bundle if none found.
    """
    last_merged_certificates_path.parent.mkdir(parents=True, exist_ok=True)

    # Collect all cert files from candidate folders
    all_cert_files: list[Path] = []
    for folder in candidate_folders:
        if folder.exists() and folder.is_dir():
            all_cert_files.extend(
                list(folder.glob("*.pem")) + list(folder.glob("*.crt"))
            )

    if not all_cert_files:
        # No custom certs → use certifi bundle only
        certifi_bundle = Path(certifi.where())
        last_merged_certificates_path.write_bytes(certifi_bundle.read_bytes())
        print(
            f"Created default merged bundle from certifi: {last_merged_certificates_path}"
        )
        return last_merged_certificates_path

    # Temporary folder to merge from collected files
    temp_folder = Path("tmp/certificates/_collected_certs")
    temp_folder.mkdir(parents=True, exist_ok=True)

    for cert_file in all_cert_files:
        target = temp_folder / cert_file.name
        target.write_bytes(cert_file.read_bytes())

    return merge_certificates(temp_folder, last_merged_certificates_path)


# Auto-initialize bundle on first run
if not last_merged_certificates_path.exists() or __name__ == "__main__":
    initialize_default_bundle([Path("certificates"), Path("data")])
