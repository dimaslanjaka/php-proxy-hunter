import os
import sys

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

from src.func_console import blue, cyan, green, magenta, red, white, yellow

cwd = os.getcwd()
print(
    f"Run this script with: `{cwd}/bin/py -u {__file__} > tmp/logs/color-function.log`"
)

print("Use helper functions:")
print(red("Red"))
print(green("Green"))
print(yellow("Yellow"))
print(blue("Blue"))
print(magenta("Magenta"))
print(cyan("Cyan"))
print(white("White"))
