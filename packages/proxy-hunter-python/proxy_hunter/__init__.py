from .cidr2ips import list_ips_from_cidr
from .curl import (
    build_request,
    generate_netscape_cookie_jar,
    get_pc_useragent,
    is_prox,
    join_header_words,
    lwp_cookie_str,
    random_windows_ua,
    update_cookie_jar,
)
from .extractor import extract_ips, extract_proxies, extract_proxies_from_file
from .ip2cidr import calculate_cidr
from .ip2proxy_list import generate_ip_port_pairs
from .ip2subnet import get_default_subnet_mask, get_subnet_mask
from .Proxy import Proxy, dict_to_proxy_list
from .proxyhunter import scan, target
from .utils import (
    IterationHelper,
    check_raw_headers_keywords,
    contains_ansi_codes,
    copy_file,
    copy_folder,
    count_lines_in_file,
    decompress_requests_response,
    delete_path,
    delete_path_if_exists,
    file_append_str,
    file_move_lines,
    file_remove_empty_lines,
    fix_permissions,
    flatten_and_clean,
    get_random_folder,
    is_valid_ip,
    is_valid_ip_connection,
    is_valid_proxy,
    is_valid_url,
    is_vps,
    list_files_in_directory,
    md5,
    read_all_text_files,
    read_file,
    remove_ansi,
    remove_non_ascii,
    remove_string_from_file,
    remove_trailing_hyphens,
    resolve_folder,
    resolve_parent_folder,
    sanitize_filename,
    serialize,
    truncate_file_content,
    write_file,
    write_json,
)
