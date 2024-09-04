from proxy_hunter import build_request

if __name__ == "__main__":
    # URL for the API request
    url = "https://api.ipgeolocation.io/ipgeo?include=hostname&ip=125.167.76.230"

    # Headers including CORS and Referer
    headers = {
        "Origin": "https://ipgeolocation.io",
        "Referer": "https://ipgeolocation.io/",
        "Access-Control-Allow-Origin": "*",
    }

    # Sending the GET request
    response = build_request(endpoint=url, headers=headers)

    # Checking if the request was successful
    if response.status_code == 200:
        # Parsing and printing the JSON response
        data = response.json()  # data.get('ip') data.get('time_zone')
        print(data)
    else:
        print(f"Error: {response.status_code}")
