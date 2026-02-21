from pathlib import Path
import certifi


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

    return output
