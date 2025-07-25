from .ansi import contains_ansi_codes, remove_ansi
from .file import (
    copy_file,
    copy_folder,
    count_lines_in_file,
    delete_path,
    delete_path_if_exists,
    file_append_str,
    file_move_lines,
    file_remove_empty_lines,
    fix_permissions,
    get_random_folder,
    is_directory_created_days_ago_or_more,
    is_file_larger_than_kb,
    list_files_in_directory,
    load_tuple_from_file,
    md5,
    move_string_between,
    read_all_text_files,
    read_file,
    remove_duplicate_line_from_file,
    remove_non_ascii,
    remove_string_from_file,
    remove_trailing_hyphens,
    resolve_folder,
    resolve_parent_folder,
    sanitize_filename,
    save_tuple_to_file,
    serialize,
    size_of_list_in_mb,
    truncate_file_content,
    write_file,
    write_json,
    file_exists,
)
from .index_utils import (
    base64_decode,
    base64_encode,
    check_raw_headers_keywords,
    clean_dict,
    decompress_requests_response,
    get_random_dict,
    get_random_item_list,
    get_unique_dicts_by_key_in_list,
    is_class_has_parameter,
    is_valid_ip,
    is_valid_ip_connection,
    is_valid_proxy,
    is_valid_url,
    is_vps,
    keep_alphanumeric_and_remove_spaces,
    md5,
    split_list_into_chunks,
    unique_non_empty_strings,
)
from .iterationHelper import IterationHelper
from .list import flatten_and_clean
from .regex_utils import find_substring_from_regex, is_matching_regex
from .dict_helper import dict_updater
