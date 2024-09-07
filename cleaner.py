import os
import random
import re
import tempfile
import time
from datetime import datetime, timedelta

# Directory of the current script
current_dir = os.path.dirname(os.path.abspath(__file__))


def clean_files_one_week_ago():
    # Directories where JSON files are located
    subdirectories = [
        "config",
        ".cache",
        "tmp",
        "tmp/cookies",
        "tmp/sessions",
        "tmp/runners",
        "tmp/logs",
        "backups",
    ]

    # Create full paths for each subdirectory
    directories = [os.path.join(current_dir, subdir) for subdir in subdirectories]

    # Get the current timestamp
    current_time = time.time()

    # Calculate the timestamp for 1 week ago
    one_week_ago = current_time - 7 * 24 * 60 * 60  # 1 week in seconds

    for directory in directories:
        if not os.path.exists(directory):
            continue
        # Loop through each file in the directory
        for file_name in os.listdir(directory):
            file_path = os.path.realpath(os.path.join(directory, file_name))
            if not os.path.isfile(file_path):
                continue
            # Skip database files (.db, .sqlite, .sqlite3)
            if re.search(r"\.(db|sqlite|sqlite3|mmdb)$", file_path, re.IGNORECASE):
                print(f"{file_path} excluded")
                continue

            # Get the last modification time of the file
            file_mtime = os.path.getmtime(file_path)

            # File was last modified more than 1 week ago
            if file_mtime < one_week_ago:
                # Remove the file
                result = os.remove(file_path)
                print(
                    f"File {file_name} removed ({'success' if result is None else 'failed'})"
                )


def cleanup_old_backups(backup_dir):
    if not os.path.exists(backup_dir):
        print(f"cleanup_old_backups: {backup_dir} not exist")
        return
    # Regex to extract date and category from the filename
    pattern = r"(python|php)_database_backup_(\d{4}-\d{2}-\d{2})\.sql"

    # Dictionary to store the latest files for each category
    latest_files = {}

    # Loop through the files in the directory
    for filename in os.listdir(backup_dir):
        match = re.match(pattern, filename)
        if match:
            category = match.group(1)
            date_str = match.group(2)
            date = datetime.strptime(date_str, "%Y-%m-%d")

            # Compare the current file's date with the stored one
            if category not in latest_files or latest_files[category]["date"] < date:
                latest_files[category] = {"filename": filename, "date": date}

    # Delete all files that are not the latest ones
    for filename in os.listdir(backup_dir):
        match = re.match(pattern, filename)
        if match:
            category = match.group(1)
            if filename != latest_files[category]["filename"]:
                os.remove(os.path.join(backup_dir, filename))
                print(f"Deleted: {filename}")

    # Output the remaining files
    for category, info in latest_files.items():
        print(f"Kept: {info['filename']}")


def test_clean_backups():
    # Create a temporary directory to mock files for testing
    with tempfile.TemporaryDirectory() as temp_dir:
        print(f"Testing in temporary directory: {temp_dir}")

        # Define start and end date for random date generation
        start_date = datetime(2023, 1, 1)
        end_date = datetime(2024, 12, 31)

        # Generate random dates for backup files
        num_files = 10  # Total number of mock files to create
        categories = ["python", "php"]

        random_dates = []
        for _ in range(num_files):
            random_days = random.randint(0, (end_date - start_date).days)
            random_date = start_date + timedelta(days=random_days)
            random_dates.append(random_date)
        backup_files = [
            f"{random.choice(categories)}_database_backup_{date.strftime('%Y-%m-%d')}.sql"
            for date in random_dates
        ]

        # Create these mock files in the temp_dir
        for file in backup_files:
            open(os.path.join(temp_dir, file), "w").close()

        print("Created files:", backup_files)

        # Run the cleanup function
        cleanup_old_backups(temp_dir)

        # Show remaining files
        remaining_files = os.listdir(temp_dir)
        print("Remaining files:", remaining_files)


if __name__ == "__main__":
    cleanup_old_backups(os.path.join(current_dir, "backups"))
    clean_files_one_week_ago()
