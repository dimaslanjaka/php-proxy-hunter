#!/bin/bash

# Get the directory where the script is located
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"

create() {
  local domain="$1"
  echo -e "$domain Global Root GC CA\n===============================" > "$SCRIPT_DIR/$domain.pem"
  openssl s_client -showcerts -connect "$domain:443" </dev/null 2>/dev/null | openssl x509 -outform PEM >> "$SCRIPT_DIR/$domain.pem"
}

# Remove existing cacert.pem if it exists
rm -f "$SCRIPT_DIR/cacert.pem"

# Download curl.pem from https://curl.haxx.se/ca/cacert.pem
curl -L --output "$SCRIPT_DIR/curl.pem" "https://curl.haxx.se/ca/cacert.pem"

# Create PEM files for each domain
create "www.google.com"
create "www.instagram.com"
create "www.facebook.com"
create "translate.google.co.id"
create "api.facebook.com"
create "graph.facebook.com"
create "graph.beta.facebook.com"
create "developers.facebook.com"
create "facebook.com"

# Concatenate all *.pem files into cacert.pem
cat "$SCRIPT_DIR"/*.pem > "$SCRIPT_DIR/cacert.pem"
