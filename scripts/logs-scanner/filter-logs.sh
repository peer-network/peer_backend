#!/bin/bash

SEARCH_DIR="./"

# array of strings to match
SEARCH_STRINGS=(
    "NO FRIENDS FOUND OR AN ERROR OCCURRED IN FETCHING FRIENDS" 
    "FFMpeg\\Exception\\ExecutableNotFoundException"
)

for file in "$SEARCH_DIR"/*.log; do
  [ -e "$file" ] || continue  # skip if no .log files exist
  for pattern in "${SEARCH_STRINGS[@]}"; do
    if grep -q "$pattern" "$file"; then
      echo "Deleting $file (matched: $pattern)"
      rm "$file"
      break  # stop checking other patterns for this file
    fi
  done
done
