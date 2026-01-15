import os
import subprocess

# Get the directory where the script is located
SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))


def create_certificate(domain):
    with open(f"{SCRIPT_DIR}/{domain}.pem", "w") as pem_file:
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


# Remove specific .pem files if they exist
for file in os.listdir(SCRIPT_DIR):
    if file.endswith(".pem") and file not in [
        "cacert.pem",
        "isrgrootx1.pem",
        "curlhaxx_cacert.pem",
    ]:
        os.remove(os.path.join(SCRIPT_DIR, file))

# Download curl.pem from https://curl.haxx.se/ca/cacert.pem
subprocess.run(
    [
        "curl",
        "-L",
        "--output",
        f"{SCRIPT_DIR}/curlhaxx_cacert.pem",
        "https://curl.haxx.se/ca/cacert.pem",
    ]
)

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
]
unique_domains = list(set(domains))

for domain in unique_domains:
    create_certificate(domain)

# Concatenate all *.pem files except cacert.pem into cacert.pem
with open(f"{SCRIPT_DIR}/cacert.pem", "w") as cacert:
    for file in os.listdir(SCRIPT_DIR):
        if file.endswith(".pem") and file != "cacert.pem":
            with open(os.path.join(SCRIPT_DIR, file), "r") as f:
                cacert.write(f.read())

# Remove all .pem files except specific ones
for file in os.listdir(SCRIPT_DIR):
    if file.endswith(".pem") and file not in [
        "cacert.pem",
        "isrgrootx1.pem",
        "curlhaxx_cacert.pem",
    ]:
        os.remove(os.path.join(SCRIPT_DIR, file))

# Remove invalid certificate blocks from cacert.pem
with open(f"{SCRIPT_DIR}/cacert.pem", "r") as cacert, open(
    f"{SCRIPT_DIR}/cacert_clean.pem", "w"
) as clean_cacert:
    cert = ""
    in_cert = False
    for line in cacert:
        if "-----BEGIN CERTIFICATE-----" in line:
            in_cert = True
            cert = ""
        if in_cert:
            cert += line
        if "-----END CERTIFICATE-----" in line:
            in_cert = False
            if (
                "BEGIN RSA PRIVATE KEY" not in cert
                and "BEGIN DSA PRIVATE KEY" not in cert
            ):
                clean_cacert.write(cert)

if os.path.exists(f"{SCRIPT_DIR}/cacert.pem"):
    os.remove(f"{SCRIPT_DIR}/cacert.pem")
os.rename(f"{SCRIPT_DIR}/cacert_clean.pem", f"{SCRIPT_DIR}/cacert.pem")

# Remove duplicate certificates
with open(f"{SCRIPT_DIR}/cacert.pem", "r") as cacert, open(
    f"{SCRIPT_DIR}/cacert_clean.pem", "w"
) as clean_cacert:
    cert = ""
    in_cert = False
    seen = set()
    for line in cacert:
        if "-----BEGIN CERTIFICATE-----" in line:
            in_cert = True
            cert = ""
        if in_cert:
            cert += line
            if "-----END CERTIFICATE-----" in line:
                in_cert = False
                if cert not in seen:
                    clean_cacert.write(cert)
                    seen.add(cert)

if os.path.exists(f"{SCRIPT_DIR}/cacert.pem"):
    os.remove(f"{SCRIPT_DIR}/cacert.pem")
os.rename(f"{SCRIPT_DIR}/cacert_clean.pem", f"{SCRIPT_DIR}/cacert.pem")

# Validate merged certificate
subprocess.run(
    ["openssl", "x509", "-in", f"{SCRIPT_DIR}/cacert.pem", "-text", "-noout"]
)
