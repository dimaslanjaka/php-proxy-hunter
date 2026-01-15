#!/bin/bash

# Get the directory where the script is located
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"

create() {
  local domain="$1"
  echo -e "$domain Global Root GC CA\n===============================" > "$SCRIPT_DIR/$domain.pem"
  openssl s_client -showcerts -connect "$domain:443" </dev/null 2>/dev/null | openssl x509 -outform PEM >> "$SCRIPT_DIR/$domain.pem"
}

# Remove specific .pem files if they exist
for file in "$SCRIPT_DIR"/*.pem; do
  filename=$(basename "$file")
  if [[ $filename != "cacert.pem" && $filename != "isrgrootx1.pem" && $filename != "curlhaxx_cacert.pem" ]]; then
    rm "$file"
  fi
done

# Download curl.pem from https://curl.haxx.se/ca/cacert.pem
curl -L --output "$SCRIPT_DIR/curlhaxx_cacert.pem" "https://curl.haxx.se/ca/cacert.pem"

# Create PEM files for each domain
create "www.google.com"
create "www.instagram.com"
create "www.facebook.com"
create "translate.google.co.id"
create "translate.google.com"
create "api.facebook.com"
create "graph.facebook.com"
create "graph.beta.facebook.com"
create "developers.facebook.com"
create "facebook.com"
create "bing.com"
create "webmanajemen.com"
create "dash.cloudflare.com"
create "www.cloudflare.com"
create "cloudflare.com"
create "api.myxl.xlaxiata.co.id"
create "otp.api.axis.co.id"
create "nq.api.axis.co.id"
create "axis.co.id"
create "httpbin.org"
create "www.httpbin.org"

# Concatenate all *.pem files except cacert.pem into cacert.pem
find "$SCRIPT_DIR" -maxdepth 1 -name '*.pem' ! -name 'cacert.pem' -exec cat {} + > "$SCRIPT_DIR/cacert.pem"

# Remove all .pem files except specific ones
for file in "$SCRIPT_DIR"/*.pem; do
  filename=$(basename "$file")
  if [[ $filename != "cacert.pem" && $filename != "isrgrootx1.pem" && $filename != "curlhaxx_cacert.pem" ]]; then
    rm "$file"
  fi
done

# Remove invalid certificate blocks from cacert.pem
awk '
  BEGIN {
    cert = ""
    in_cert = 0
  }
  /^-----BEGIN CERTIFICATE-----$/ {
    in_cert = 1
    cert = ""
  }
  in_cert {
    cert = cert $0 "\n"
  }
  /^-----END CERTIFICATE-----$/ {
    in_cert = 0
    if (length(cert) > 0) {
      if (index(cert, "BEGIN RSA PRIVATE KEY") == 0 && index(cert, "BEGIN DSA PRIVATE KEY") == 0) {
        print cert
      }
    }
  }
' "$SCRIPT_DIR/cacert.pem" > "$SCRIPT_DIR/cacert_clean.pem" && \
mv "$SCRIPT_DIR/cacert_clean.pem" "$SCRIPT_DIR/cacert.pem"

# remove duplicate certificates

awk '
/-----BEGIN CERTIFICATE-----/ {
    cert = "";
    in_cert = 1;
}
in_cert {
    cert = cert $0 "\n";
    if (/-----END CERTIFICATE-----/) {
        if (!seen[cert]) {
            print cert;
            seen[cert] = 1;
        }
        in_cert = 0;
    }
    next;
}
!in_cert
' < "$SCRIPT_DIR/cacert.pem" > "$SCRIPT_DIR/cacert_clean.pem" && \
mv "$SCRIPT_DIR/cacert_clean.pem" "$SCRIPT_DIR/cacert.pem"

# validate merged certificate

openssl x509 -in "$SCRIPT_DIR/cacert.pem" -text -noout
