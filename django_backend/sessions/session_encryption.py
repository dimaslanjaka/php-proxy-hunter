import socket


class session_encryption:
    def __init__(self, key=socket.gethostname()):
        self.key = key
        self.f9939a = list(range(256))

    def a(self, text):
        self.f9939a = list(range(256))
        i_arr = [ord(self.key[i % len(self.key)]) for i in range(256)]

        # Initialization phase
        i2 = 0
        for i3 in range(256):
            i4 = self.f9939a[i3]
            i2 = (i2 + i4 + i_arr[i3]) % 256
            self.f9939a[i3], self.f9939a[i2] = self.f9939a[i2], self.f9939a[i3]

        # Processing phase
        sb = []
        i5 = 0
        i6 = 0
        for i7 in range(len(text)):
            i5 = (i5 + 1) % 256
            i8 = self.f9939a[i5]
            i6 = (i6 + i8) % 256
            self.f9939a[i5], self.f9939a[i6] = self.f9939a[i6], self.f9939a[i5]
            sb.append(chr(self.f9939a[(self.f9939a[i5] + i8) % 256] ^ ord(text[i7])))

        return "".join(sb)

    def decrypt(self, encrypted):
        sb = []
        i = 0
        while i < len(encrypted):
            try:
                i2 = i + 2
                sb.append(chr(int(encrypted[i:i2], 16)))
                i = i2
            except ValueError:
                break

        return self.a("".join(sb))

    def encrypt(self, text):
        encrypted = self.a(text)
        return "".join(f"{ord(c):02x}" for c in encrypted)
