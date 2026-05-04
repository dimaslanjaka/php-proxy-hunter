import os
import sys

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

from src.func_console import color_percent_value_text, rainbow

if __name__ == "__main__":
    cwd = os.getcwd()
    print(
        f"Run this script with: `{cwd}/bin/py -u {__file__} > tmp/logs/color-meter.log`"
    )

    print("Use helper functions:")
    # Print gradient samples from 0% to 100% every 10%
    for i in range(0, 101, 10):
        print(color_percent_value_text(i, f"{i}%"))

    # console = Console()

    text = "SMOOTH RAINBOW USING RICH"
    print(rainbow(text))
