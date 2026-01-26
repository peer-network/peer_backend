set -euo pipefail

usage() {
  cat <<'USAGE'
Usage: make_server_archive.sh [options]

Options:
  -d <date>            Archive date, format YYYYMMDD (default: today)
  -i <db_host>         Database host/IP (default: 10.5.2.186)
  -p <db_port>         Database port (default: 5432)
  -u <db_user>         Database user (default: root)
  -n <db_name>         Database name (default: peer3)
  -r <runtime_path>    Runtime data path (default: /var/www/peer_beta/peer_backend/runtime-data)
  -s <server_prefix>   Server archive name prefix (default: staging-server-data)
  -t <runtime_prefix>  Runtime archive name prefix (default: staging-runtime-data)
  -h                   Show this help and exit
USAGE
}

# Default archive timestamp includes day/hour/minute to avoid collisions
DATE=$(date +%Y%m%d%H%M)
DB_IP='10.5.2.186'
DB_PORT='5432'
DB_USER='root'
DB_NAME='peer3'
RUNTIME_DATA_PATH='/var/www/peer_beta/peer_backend/runtime-data'
SERVER_DATA_NAME_BASE='staging-server-data'
RUNTIME_DATA_ARCHIVE_NAME_BASE='staging-runtime-data'

while getopts ":d:i:p:u:n:r:s:t:h" opt; do
  case "$opt" in
    d) DATE="$OPTARG" ;;
    i) DB_IP="$OPTARG" ;;
    p) DB_PORT="$OPTARG" ;;
    u) DB_USER="$OPTARG" ;;
    n) DB_NAME="$OPTARG" ;;
    r) RUNTIME_DATA_PATH="$OPTARG" ;;
    s) SERVER_DATA_NAME_BASE="$OPTARG" ;;
    t) RUNTIME_DATA_ARCHIVE_NAME_BASE="$OPTARG" ;;
    h)
      usage
      exit 0
      ;;
    :)
      echo "Option -$OPTARG requires an argument." >&2
      usage
      exit 1
      ;;
    \?)
      echo "Invalid option -$OPTARG" >&2
      usage
      exit 1
      ;;
  esac
done

RUNTIME_DATA_ARCHIVE_NAME="${RUNTIME_DATA_ARCHIVE_NAME_BASE}-${DATE}"
SERVER_DATA_NAME="${SERVER_DATA_NAME_BASE}-${DATE}"
ARCHIVE_FOLDER_NAME="${SERVER_DATA_NAME_BASE}-${DATE}"
DB_DUMP_NAME="staging-${DATE}.dump"
ARCHIVE_NAME="${ARCHIVE_FOLDER_NAME}.tar"

echo "Using archive timestamp ${DATE}"
echo "Dumping database ${DB_NAME} from ${DB_IP}:${DB_PORT} as ${DB_USER}"
pg_dump -U "${DB_USER}" -h "${DB_IP}" -p "${DB_PORT}" -F c -b -v -f "${DB_DUMP_NAME}" "${DB_NAME}"

# echo "Copying runtime data from ${RUNTIME_DATA_PATH}"
# rsync -a --progress "${RUNTIME_DATA_PATH}/" "./${RUNTIME_DATA_ARCHIVE_NAME}/"

echo "Server data archive prepared: ${SERVER_DATA_NAME}"
mkdir -p "${ARCHIVE_FOLDER_NAME}"
echo "Archiving ${ARCHIVE_NAME}"
tar -cf "${ARCHIVE_NAME}" "${RUNTIME_DATA_PATH}"

echo "Checking archive integrity"
if tar -tf "${ARCHIVE_NAME}" >/dev/null; then
  echo "Archive ${ARCHIVE_NAME} looks good"
else
  echo "Failed to verify ${ARCHIVE_NAME}" >&2
  exit 1
fi


mv "${DB_DUMP_NAME}" "${ARCHIVE_FOLDER_NAME}/"
mv "${ARCHIVE_NAME}" "${ARCHIVE_FOLDER_NAME}/"
