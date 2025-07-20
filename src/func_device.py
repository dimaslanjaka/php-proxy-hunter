import wmi
import subprocess
from typing import Optional
import platform
import uuid
from proxy_hunter import md5


def split_serial_number_md5(md5_hash: str) -> str:
    """
    Split an MD5 hash into a UUID style format.

    Parameters:
        md5_hash (str): The MD5 hash to split.

    Returns:
        str: The MD5 hash split into a UUID-style format.
    """
    # Convert the MD5 hash to a UUID object
    uuid_obj = uuid.UUID(md5_hash)

    # Get the hexadecimal representation of the UUID
    hex_str = uuid_obj.hex

    # Split the hexadecimal string into five groups of varying lengths
    parts = [hex_str[:8], hex_str[8:12], hex_str[12:16], hex_str[16:20], hex_str[20:]]

    # Join the parts with dashes to form the UUID-style string
    uuid_style = "-".join(parts)

    return uuid_style


def get_serial_number_uuid():
    return split_serial_number_md5(
        md5(
            f"{platform.system()} | {platform.machine()} | {platform.processor()} | {get_serial_number()}"
        )
    )


def get_serial_number() -> Optional[str]:
    """
    Retrieve the serial number of the device.

    Returns:
        str: The serial number if successfully retrieved, otherwise None.
    """
    system = platform.system()
    try:
        if system == "Windows":
            wmi_obj = wmi.WMI()
            os_info = wmi_obj.Win32_OperatingSystem()[0]
            return os_info.SerialNumber
        elif system == "Linux":
            return (
                subprocess.check_output(
                    "sudo dmidecode -s system-serial-number", shell=True
                )
                .decode()
                .strip()
            )
        elif system == "Darwin":  # macOS
            return (
                subprocess.check_output(
                    "ioreg -l | grep IOPlatformSerialNumber", shell=True
                )
                .decode()
                .strip()
                .split('"')[3]
            )
        else:
            print("Unsupported operating system.")
            return None
    except Exception as e:
        print("Error:", e)
        return None


if __name__ == "__main__":
    serial_number: Optional[str] = get_serial_number()
    if serial_number:
        print("Serial Number:", serial_number)
    else:
        print("Failed to retrieve Serial Number.")
