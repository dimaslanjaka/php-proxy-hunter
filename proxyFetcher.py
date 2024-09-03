from src.func import *
from src.func_proxy import *
from src.requests_cache import get_with_proxy
from datetime import datetime as dt


def proxyFetcher():
    urls = json.loads(read_file(get_relative_path("proxyFetcherSources.json")))

    results: List[str] = []
    file_prefix = "added-fetch-" + dt.now().strftime("%Y%m%d")
    directory = get_relative_path("assets/proxies/")
    for filename in os.listdir(directory):
        filepath = os.path.join(directory, filename)
        if os.path.isfile(filepath) and filename.startswith(file_prefix):
            class_list = extract_proxies_from_file(filepath)
            results.extend(
                list(set(obj.proxy for obj in class_list)) if class_list else []
            )

    for url in urls:
        try:
            # response = build_request(endpoint=url, no_cache=True)
            response = get_with_proxy(url, cache_expiration=5 * 60 * 60)
            if response and response.ok:
                text = decompress_requests_response(response)
                class_list = extract_proxies(text)
                proxy_list = list(set(obj.proxy for obj in class_list))
                results.extend(proxy_list)
        except Exception as e:
            print(f"fail fetch proxy from {url}: {e}")

    # Ensure 'results' contains only unique values
    results = list(set(results))

    print(f"got {len(results)} proxies")

    # split list into chunks with [n] items each chunk

    def split_list(lst, chunk_size):
        return [lst[i : i + chunk_size] for i in range(0, len(lst), chunk_size)]

    chunk_size = 10000
    chunks = split_list(results, chunk_size)

    # Print the number of chunks and the size of each chunk for verification
    print(f"Total chunks: {len(chunks)}")
    for i, chunk in enumerate(chunks):
        print(f"Chunk {i} size: {len(chunk)}")
        nf = os.path.join(directory, f"{file_prefix}-chunk-{i}.txt")
        write_file(nf, "\n".join(chunk) + "\n\n")


if __name__ == "__main__":
    proxyFetcher()
