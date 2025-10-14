#!/bin/bash

# Exit on error
set -e

# --- Arguments ---
BRANCH1=$1
BRANCH2=$2

if [ -z "$BRANCH1" ] || [ -z "$BRANCH2" ]; then
  echo "Usage: $0 <branch1> <branch2>"
  exit 1
fi

# --- Setup output folder ---
ROOT=changelog/
TIMESTAMP=$(date +"%Y%m%d-%H%M%S")
OUTPUT_DIR="${ROOT}/diffs-${TIMESTAMP}-${BRANCH1}-${BRANCH2}"
PHP_DIR="${OUTPUT_DIR}/php"
SQL_DIR="${OUTPUT_DIR}/sql"
API_DIR="${OUTPUT_DIR}/api"
OTHER_DIR="${OUTPUT_DIR}/others"

mkdir -p "$PHP_DIR"
mkdir -p "$SQL_DIR"
mkdir -p "$OTHER_DIR"
mkdir -p "$API_DIR"

# --- Get changed files ---
FILES=$(git diff --name-only "$BRANCH1...$BRANCH2")

# --- Create diffs ---
for FILE in $FILES; do
  # Skip if file was deleted
  if ! git ls-tree -r --name-only "$BRANCH2" | grep -qx "$FILE"; then
    echo "Skipping deleted file: $FILE"
    echo ${FILE} >> "${OUTPUT_DIR}/deleted.diff"
    continue
  fi

  # Escape slashes and create file name
  SAFE_NAME=$(echo "$FILE" | tr '/' '_')
  EXT="${FILE##*.}"

  # Choose target dir
  if [ "$EXT" = "graphql" ] || [ "$EXT" = "graphql"  ]; then
    TARGET="$API_DIR/$SAFE_NAME.diff"
  elif [ "$EXT" = "php" ]; then
    TARGET="$PHP_DIR/$SAFE_NAME.diff"
  elif [ "$EXT" = "sql" ]; then
    TARGET="$SQL_DIR/$SAFE_NAME.diff"
  else
    TARGET="$OTHER_DIR/$SAFE_NAME.diff"
  fi

  # Save diff
  git diff "$BRANCH1...$BRANCH2" -- "$FILE" > "$TARGET"
done

echo "Diffs saved in: $OUTPUT_DIR"
