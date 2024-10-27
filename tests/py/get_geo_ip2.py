import os
import sys

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "../..")))

from src.geoPlugin import (
    get_geo_ip2,
    get_timezones_by_country_code,
    get_timezones_by_lat_lon,
)


if __name__ == "__main__":
    geo = get_geo_ip2("184.185.2.12:4145")
    tzc = None
    if geo:
        if geo.country_code:
            tzc = get_timezones_by_country_code(geo.country_code)
            print(f"Timezone from country code:\t\t{tzc}")
        if geo.latitude and geo.longitude:
            tzl = get_timezones_by_lat_lon(geo.latitude, geo.longitude)
            print(f"Timezone from latitude and longitude:\t{tzc}")
