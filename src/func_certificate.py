import os
import sys

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))
import re
import ssl
from typing import List

import certifi
import requests

from src.func import get_nuitka_file

output_pem = get_nuitka_file("data/cacert.pem")
# Set the certificate file in environment variables
os.environ["REQUESTS_CA_BUNDLE"] = output_pem
os.environ["SSL_CERT_FILE"] = output_pem

# Replace create default https context method
ssl._create_default_https_context = lambda: ssl.create_default_context(
    cafile=output_pem
)


def extract_certificates(pem_file):
    """
    Extracts individual certificates from a PEM file.

    Args:
        pem_file (str): Path to the PEM file.

    Returns:
        list of str: A list of certificate strings extracted from the PEM file.
    """
    with open(pem_file, "r") as f:
        pem_data = f.read()

    # Regular expression to match each certificate block
    cert_pattern = re.compile(
        r"-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----", re.DOTALL
    )
    certificates = cert_pattern.findall(pem_data)

    return certificates


def write_unique_certificates(pem_files: List[str], output_file):
    """
    Writes unique certificates from multiple PEM files into a single PEM file.

    Args:
        pem_files (list of str): List of paths to the PEM files.
        output_file (str): Path to the output combined PEM file.
    """
    unique_certificates = set()
    pem_files.append(certifi.where())

    for pem_file in pem_files:
        certs = extract_certificates(pem_file)
        unique_certificates.update(certs)

    with open(output_file, "w") as f:
        for cert in unique_certificates:
            f.write(cert + "\n\n")


def get_pem_files_from_directory(directory):
    """
    Retrieves a list of PEM files from the specified directory.

    Args:
        directory (str): Path to the directory.

    Returns:
        list of str: A list of paths to the PEM files in the directory.
    """
    pem_files = []
    for file in os.listdir(directory):
        if file.endswith(".pem"):
            pem_files.append(os.path.join(directory, file))
    return pem_files


def merge_pem():
    # Directory containing PEM files
    directory = get_nuitka_file("data")

    # Get list of PEM files from the directory
    pem_files = get_pem_files_from_directory(directory)
    print(f"total pem files {len(pem_files)}")

    # Write unique certificates to the combined PEM file
    write_unique_certificates(pem_files, output_pem)


def main():
    merge_pem()
    # Use the combined PEM file with requests
    url = "https://example.com"
    response = requests.get(url, verify=output_pem)

    # Output the response status and text
    print(f"status code {response.status_code}")


if __name__ == "__main__":
    main()
