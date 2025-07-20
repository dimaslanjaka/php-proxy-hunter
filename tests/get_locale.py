import sys
import os
from babel import languages

# Add parent directory to the Python path
sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

from src.geoPlugin import get_locale_from_country_code
from src.func_proxy import *
from src.func import *

if __name__ == "__main__":
    country_code = "ID"
    language_country = get_locale_from_country_code(country_code)
    print(f"The language for country code {country_code} is {language_country}.")
