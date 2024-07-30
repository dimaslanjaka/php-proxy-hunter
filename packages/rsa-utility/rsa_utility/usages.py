import os
from rsa_utility import RSAEncryption

# Example usage
rsa_helper = RSAEncryption()

# Create necessary directories
os.makedirs("tmp/data", exist_ok=True)

# Regenerate RSA keys and certificate
rsa_helper.regenerate_certificate_and_keys(
    "tmp/data/certificate.pem", "tmp/data/private_key.pem"
)

# Load the updated private key
rsa_helper.load_key_from_pem("tmp/data/private_key.pem")

# Encrypt and decrypt using the new keys
encrypted = rsa_helper.encrypt("Hello, World!")
print(f"Encrypted: {encrypted}")

decrypted = rsa_helper.decrypt(encrypted)
print(f"Decrypted: {decrypted}")
