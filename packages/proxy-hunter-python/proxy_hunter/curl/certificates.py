from pathlib import Path
import certifi

last_merged_certificates_path = Path("tmp/certificates/merged_certificates.pem")


def merge_certificates(folder_path: str | Path, output_path: str | Path) -> Path:
    """
    Merge certifi CA bundle with all .pem and .crt files
    found inside `folder_path`.

    Returns the output bundle path.
    """
    folder = Path(folder_path)
    output = Path(output_path)

    if not folder.exists() or not folder.is_dir():
        raise ValueError(f"Invalid folder path: {folder}")

    certifi_bundle = Path(certifi.where())

    # Collect certificate files
    cert_files = list(folder.glob("*.pem")) + list(folder.glob("*.crt"))

    if not cert_files:
        raise ValueError(f"No .pem or .crt files found in {folder}")

    with output.open("wb") as outfile:
        # Write certifi bundle first
        outfile.write(certifi_bundle.read_bytes())
        outfile.write(b"\n")

        # Append each custom certificate
        for cert_file in cert_files:
            data = cert_file.read_bytes().strip()
            if not data:
                continue

            outfile.write(data)
            outfile.write(b"\n")

    print(f"Merged bundle created: {output}")
    print(f"Added {len(cert_files)} custom certificate(s).")

    # Copy merged bundle to the known location for use in curl requests
    last_merged_certificates_path.parent.mkdir(parents=True, exist_ok=True)
    output.replace(last_merged_certificates_path)

    return output


if not last_merged_certificates_path.exists():
    # Create an initial merged bundle if it doesn't exist.
    # Look for custom certs in multiple candidate folders and merge them.
    candidate_folders = [Path("certificates"), Path("data")]

    # Collect cert files from all existing candidate folders
    cert_files = []
    for cand in candidate_folders:
        if cand.exists() and cand.is_dir():
            cert_files.extend(list(cand.glob("*.pem")) + list(cand.glob("*.crt")))

    last_merged_certificates_path.parent.mkdir(parents=True, exist_ok=True)

    if cert_files:
        certifi_bundle = Path(certifi.where())
        with last_merged_certificates_path.open("wb") as outfile:
            outfile.write(certifi_bundle.read_bytes())
            outfile.write(b"\n")

            for cert_file in cert_files:
                data = cert_file.read_bytes().strip()
                if not data:
                    continue

                outfile.write(data)
                outfile.write(b"\n")

        print(
            f"Created merged bundle including custom certs: {last_merged_certificates_path}"
        )
        print(f"Added {len(cert_files)} custom certificate(s).")
    else:
        # No custom certs found; use certifi bundle as fallback
        certifi_bundle = Path(certifi.where())
        last_merged_certificates_path.write_bytes(certifi_bundle.read_bytes())
        print(
            f"Created default merged bundle from certifi: {last_merged_certificates_path}"
        )
