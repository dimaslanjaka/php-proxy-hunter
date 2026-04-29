import os
import sys

from colorama import Back, Fore, Style

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

print(f"Run this script with: `py -u {__file__} > tmp/logs/color.log`")

print(type(sys.stdout))
print(Fore.RED + "Red" + Style.RESET_ALL)
print(
    Style.BRIGHT
    + Fore.YELLOW
    + Back.CYAN
    + "Bright yellow text on cyan background"
    + Style.RESET_ALL
)
print("\033[31m" + "Red" + "\033[m")
print("\033[1;33;46m" + "Bright yellow text on cyan background" + "\033[m")
