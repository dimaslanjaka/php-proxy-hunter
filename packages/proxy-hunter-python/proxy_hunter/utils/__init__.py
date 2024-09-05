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
    list_files_in_directory,
    load_tuple_from_file,
    md5,
    read_all_text_files,
    read_file,
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
)
from .index_utils import (
    check_raw_headers_keywords,
    decompress_requests_response,
    is_valid_ip,
    is_valid_ip_connection,
    is_valid_proxy,
    is_valid_url,
    is_vps,
)
from .iterationHelper import IterationHelper
from .list import flatten_and_clean
from .regex_utils import find_substring_from_regex, is_matching_regex
