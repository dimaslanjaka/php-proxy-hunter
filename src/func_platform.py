import platform
import importlib


def import_windows_packages():
    try:
        global win32api
        global wmi
        win32api = importlib.import_module('win32api')
        wmi = importlib.import_module('wmi')
        print("Windows-specific packages imported successfully.")
    except ImportError as e:
        print(f"Failed to import Windows-specific packages: {e}")


def import_linux_packages():
    print("No specific packages to import for Linux.")
    # Your Linux-specific code here


def main():
    if platform.system() == 'Windows':
        import_windows_packages()
        # Now you can use wmi and win32api functions here
        # Example usage of wmi
        c = wmi.WMI()
        for os in c.Win32_OperatingSystem():
            print(os.Caption, os.OSArchitecture)
    else:
        import_linux_packages()


if __name__ == "__main__":
    main()
