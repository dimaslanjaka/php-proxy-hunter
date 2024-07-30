from cryptography.hazmat.primitives import serialization
from cryptography.hazmat.primitives.asymmetric import rsa
from cryptography.hazmat.primitives import hashes
from cryptography.hazmat.primitives.asymmetric import padding
from cryptography.hazmat.backends import default_backend
from cryptography.x509 import CertificateBuilder, Name, NameAttribute
from cryptography.x509.oid import NameOID
import datetime


class RSAEncryption:
    def __init__(self):
        self.private_key = None
        self.public_key = None

    def generate_keys(self, key_size=2048):
        """
        Generate a new RSA private and public key pair.
        """
        self.private_key = rsa.generate_private_key(
            public_exponent=65537, key_size=key_size, backend=default_backend()
        )
        self.public_key = self.private_key.public_key()

    def save_key_to_pem(self, filename, is_private=True):
        """
        Save the RSA key to a PEM file.
        """
        if is_private:
            if self.private_key is None:
                raise ValueError("Private key is not generated.")
            pem = self.private_key.private_bytes(
                encoding=serialization.Encoding.PEM,
                format=serialization.PrivateFormat.TraditionalOpenSSL,
                encryption_algorithm=serialization.NoEncryption(),
            )
        else:
            if self.public_key is None:
                raise ValueError("Public key is not generated.")
            pem = self.public_key.public_bytes(
                encoding=serialization.Encoding.PEM,
                format=serialization.PublicFormat.SubjectPublicKeyInfo,
            )

        with open(filename, "wb") as f:
            f.write(pem)

    def load_key_from_pem(self, filename, is_private=True):
        """
        Load an RSA key from a PEM file.
        """
        with open(filename, "rb") as f:
            pem_data = f.read()

        if is_private:
            self.private_key = serialization.load_pem_private_key(
                pem_data, password=None, backend=default_backend()
            )
            self.public_key = self.private_key.public_key()
        else:
            self.public_key = serialization.load_pem_public_key(
                pem_data, backend=default_backend()
            )

    def encrypt(self, plaintext):
        """
        Encrypt the plaintext using the RSA public key.
        """
        if self.public_key is None:
            raise ValueError("Public key is not set.")
        ciphertext = self.public_key.encrypt(
            plaintext.encode(),
            padding.OAEP(
                mgf=padding.MGF1(algorithm=hashes.SHA256()),
                algorithm=hashes.SHA256(),
                label=None,
            ),
        )
        return ciphertext

    def decrypt(self, ciphertext):
        """
        Decrypt the ciphertext using the RSA private key.
        """
        if self.private_key is None:
            raise ValueError("Private key is not set.")
        plaintext = self.private_key.decrypt(
            ciphertext,
            padding.OAEP(
                mgf=padding.MGF1(algorithm=hashes.SHA256()),
                algorithm=hashes.SHA256(),
                label=None,
            ),
        )
        return plaintext.decode()

    def generate_self_signed_certificate(
        self, cert_filename, key_filename, common_name="localhost"
    ):
        """
        Generate a self-signed certificate and save it to a PEM file.
        """
        if self.private_key is None or self.public_key is None:
            raise ValueError("Keys must be generated before creating a certificate.")

        subject = Name([NameAttribute(NameOID.COMMON_NAME, common_name)])
        builder = CertificateBuilder(
            issuer_name=subject,
            subject_name=subject,
            public_key=self.public_key,
            serial_number=1,
            not_valid_before=datetime.datetime.utcnow(),
            not_valid_after=datetime.datetime.utcnow() + datetime.timedelta(days=365),
            extensions=[],
        )

        certificate = builder.sign(
            private_key=self.private_key,
            algorithm=hashes.SHA256(),
            backend=default_backend(),
        )

        with open(cert_filename, "wb") as f:
            f.write(certificate.public_bytes(serialization.Encoding.PEM))

    def regenerate_certificate_and_keys(self, cert_filename, key_filename):
        """
        Regenerate RSA keys and a self-signed certificate.
        """
        self.generate_keys()
        self.save_key_to_pem(key_filename, is_private=True)
        self.save_key_to_pem(
            key_filename.replace("private_key", "public_key"), is_private=False
        )
        self.generate_self_signed_certificate(cert_filename, key_filename)
