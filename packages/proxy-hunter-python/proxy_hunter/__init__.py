from .Proxy import Proxy, dict_to_proxy_list
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
    is_port_open,
    get_device_ip,
    check_proxy,
    get_requests_error,
    ProxyCheckResult,
    time2isoz,
)
from .extractor import (
    extract_ips,
    extract_proxies,
    extract_proxies_from_file,
    extract_url,
)
from .ip2cidr import calculate_cidr
from .ip2proxy_list import generate_ip_port_pairs
from .ip2subnet import get_default_subnet_mask, get_subnet_mask
from .proxyhunter import scan, target
from .proxyhunter2 import gen_ports, iterate_gen_ports
from .utils import (
    IterationHelper,
    check_raw_headers_keywords,
    contains_ansi_codes,
    copy_file,
    copy_folder,
    find_substring_from_regex,
    is_matching_regex,
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
    load_tuple_from_file,
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
    save_tuple_to_file,
    serialize,
    truncate_file_content,
    write_file,
    write_json,
    base64_decode,
    base64_encode,
    unique_non_empty_strings,
    clean_dict,
    get_random_dict,
    get_random_item_list,
    split_list_into_chunks,
    keep_alphanumeric_and_remove_spaces,
    get_unique_dicts_by_key_in_list,
    is_class_has_parameter,
    iterationHelper,
    move_string_between,
    size_of_list_in_mb,
    is_file_larger_than_kb,
    is_directory_created_days_ago_or_more,
    remove_duplicate_line_from_file,
)
