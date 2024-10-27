import os
import sys

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "../..")))

from src.geoPlugin import get_timezones_by_country_code
from geopy.geocoders import Nominatim
from geopy.exc import GeocoderTimedOut
from pprint import pprint
from timezonefinder import TimezoneFinder


def get_timezone_from_country(country_code):
    geolocator = Nominatim(user_agent="geo_timezone")
    location = geolocator.geocode(country_code)

    if location:
        pprint(location)
        tf = TimezoneFinder()
        timezone = tf.timezone_at(lng=location.longitude, lat=location.latitude)
        return timezone
    else:
        return "Country not found."


if __name__ == "__main__":
    timezones = get_timezones_by_country_code("ID")
    tx = get_timezone_from_country("ID")
    print(tx)
