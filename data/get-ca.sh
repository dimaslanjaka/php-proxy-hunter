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

# Concatenate all *.pem files except cacert.pem into cacert.pem
find "$SCRIPT_DIR" -maxdepth 1 -name '*.pem' ! -name 'cacert.pem' -exec cat {} + > "$SCRIPT_DIR/cacert.pem"

# Remove all .pem files except specific ones
for file in "$SCRIPT_DIR"/*.pem; do
  filename=$(basename "$file")
  if [[ $filename != "cacert.pem" && $filename != "isrgrootx1.pem" && $filename != "curlhaxx_cacert.pem" ]]; then
    rm "$file"
  fi
done

# validate merged certificate

openssl x509 -in "$SCRIPT_DIR/cacert.pem" -text -noout