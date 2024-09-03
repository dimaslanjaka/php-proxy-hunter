from typing import List


def flatten_and_clean(list_of_lists: List[List[str]]) -> List[str]:
    """
    Flattens a list of lists of strings, removes duplicates, and removes empty strings.

    Args:
        list_of_lists (List[List[str]]): A list where each element is a list of strings.

    Returns:
        List[str]: A list of unique, non-empty strings from the flattened input.
    """
    # Flatten the list of lists
    flattened_list = [item for sublist in list_of_lists for item in sublist]

    # Remove duplicates and empty strings
    cleaned_set = {item for item in flattened_list if item}

    # Convert back to a list if needed
    cleaned_list = list(cleaned_set)

    return cleaned_list
