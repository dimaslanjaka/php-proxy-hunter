import os
import sys

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "../..")))

from src.geoPlugin import get_timezones_by_lat_lon


if __name__ == "__main__":
    timezones = get_timezones_by_lat_lon(-7.3558756, 112.7747886)
    print(timezones)
