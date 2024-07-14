def parse_csrf_token_from_cookie_file(cookie_file_path):
    csrf_token = None
    with open(cookie_file_path, 'r') as f:
        for line in f:
            if line.startswith('#'):
                continue  # Skip comment lines
            parts = line.strip().split('\t')
            if len(parts) >= 7 and parts[5] == 'csrftoken':
                csrf_token = parts[6]
                break
    return csrf_token
