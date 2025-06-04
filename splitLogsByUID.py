import os
import re

# Path to the log file
input_file = "2025-06-03.log"

# Output directory for uid files
output_dir = "uids"
os.makedirs(output_dir, exist_ok=True)

# Regex pattern to extract UID
uid_pattern = re.compile(r'"uid"\s*:\s*"([a-fA-F0-9]+)"')

# Dictionary to hold file handles
file_handles = {}

try:
    with open(input_file, "r") as infile:
        for line in infile:
            match = uid_pattern.search(line)
            if match:
                uid = match.group(1)
                if uid not in file_handles:
                    # Open a new file for this UID
                    file_path = os.path.join(output_dir, f"{uid}.log")
                    file_handles[uid] = open(file_path, "a")
                file_handles[uid].write(line)
finally:
    # Close all file handles
    for f in file_handles.values():
        f.close()