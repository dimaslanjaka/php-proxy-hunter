import hashlib
import os
import platform
import uuid

try:
    import winreg
except ImportError:
    winreg = None  # Not on Windows


def isWSL():
    return "microsoft" in platform.uname().release.lower()


def detect_wsl_version():
    if isWSL():
        try:
            with open("/proc/version", "r") as f:
                version_info = f.read().lower()
                if "microsoft" in version_info:
                    # This could be WSL1 or WSL2
                    with open("/proc/sys/kernel/osrelease", "r") as f2:
                        osrelease = f2.read()
                        if "microsoft-standard" in osrelease:
                            return "WSL2"
                        else:
                            return "WSL1"
        except Exception as e:
            return f"WSL (unknown version): {e}"
    return "Not WSL"


def get_linux_machine_id():
    """Get the machine ID from /etc/machine-id (Linux)."""
    try:
        with open("/etc/machine-id") as f:
            return f.read().strip()
    except Exception:
        return None


def get_windows_machine_guid():
    """Get the MachineGuid from the Windows registry."""
    if winreg is not None:
        try:
            key = winreg.OpenKey(  # type: ignore
                winreg.HKEY_LOCAL_MACHINE, r"SOFTWARE\Microsoft\Cryptography"  # type: ignore
            )  # type: ignore
            value, _ = winreg.QueryValueEx(key, "MachineGuid")  # type: ignore
            return value
        except Exception:
            return None


def get_stable_machine_hash():
    """Generate a stable SHA-256 hash based on system info and MAC address."""
    sys = platform.uname()
    mac = uuid.getnode()
    raw = f"{sys.system}-{sys.node}-{sys.release}-{mac}"
    return hashlib.sha256(raw.encode()).hexdigest()


def get_machine_identifier():
    """Get the most appropriate machine-specific identifier for current OS."""
    system = platform.system()
    if system == "Windows" and winreg:
        return get_windows_machine_guid()
    elif system == "Linux":
        return get_linux_machine_id()
    return get_stable_machine_hash()


def get_mac_uuid():
    """Return the MAC address as a UUID string."""
    return str(uuid.UUID(int=uuid.getnode()))


def get_hostname_uuid():
    """Generate UUID5 based on system hostname."""
    return str(uuid.uuid5(uuid.NAMESPACE_DNS, platform.node()))


if __name__ == "__main__":
    print("System:", platform.system())
    print("Hostname UUID5:", get_hostname_uuid())
    print("MAC UUID:", get_mac_uuid())
    print("System Identifier:", get_machine_identifier())
    print("Stable Machine Hash:", get_stable_machine_hash())
