#!/bin/bash

LOG_DIR="./"
OUT_DIR="./error_sessions"

mkdir -p "$OUT_DIR"

# loop over log files
for logfile in "$LOG_DIR"/*; do
  # extract date from filename (assuming log filename is like 2025-09-28.log)
  logfile_date=$(basename "$logfile" | cut -d. -f1)

  # find all UIDs that appear in ERROR logs for that day
  grep "peerpg.ERROR" "$logfile" | grep -oE '"uid":"[^"]+"' | cut -d: -f2 | tr -d '"' | sort -u | while read -r uid; do
    # collect all lines from that UID across the log
    grep "$uid" "$logfile" > "$OUT_DIR/${logfile_date}-${uid}.log"
  done
done