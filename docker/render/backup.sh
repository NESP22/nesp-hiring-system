#!/bin/sh
set -eu

: "${DB_HOST:?DB_HOST is required}"
: "${DB_NAME:?DB_NAME is required}"
: "${DB_USER:?DB_USER is required}"
: "${DB_PASSWORD:?DB_PASSWORD is required}"

DATA_ROOT="${DATA_ROOT:-/var/data}"
BACKUP_ROOT="${BACKUP_ROOT:-$DATA_ROOT/backups}"
RETENTION_DAYS="${RETENTION_DAYS:-7}"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
TARGET="$BACKUP_ROOT/$STAMP"

mkdir -p "$TARGET"
chmod 0700 "$BACKUP_ROOT" "$TARGET"

echo "Creating MariaDB logical dump..."
dump_file="$TARGET/database.sql"
if MYSQL_PWD="$DB_PASSWORD" mariadb-dump \
    --host="$DB_HOST" \
    --port="${DB_PORT:-3306}" \
    --user="$DB_USER" \
    --single-transaction \
    --routines \
    --events \
    --triggers \
    "$DB_NAME" > "$dump_file"; then
  gzip -9 "$dump_file"
else
  rm -f "$dump_file" "$TARGET/database.sql.gz"
  echo "MariaDB logical dump failed." >&2
  exit 1
fi

echo "Archiving persistent OpenCATS files..."
set --
for path in config.php INSTALL_BLOCK attachments uploads; do
  if [ -e "$DATA_ROOT/$path" ]; then
    set -- "$@" "$path"
  fi
done

if [ "$#" -gt 0 ]; then
  tar -C "$DATA_ROOT" -czf "$TARGET/opencats-files.tar.gz" "$@"
else
  tar -czf "$TARGET/opencats-files.tar.gz" --files-from /dev/null
fi

cat > "$TARGET/README.txt" <<EOF
NESP Hiring System local Render backup
Created: $STAMP UTC
Database: $DB_NAME

Contents:
- database.sql.gz
- opencats-files.tar.gz

IMPORTANT: This copy is stored on the same Render persistent disk as the app.
It does not satisfy the required off-platform backup gate by itself.
Copy it to encrypted off-platform storage and test restoration.
EOF

chmod 0600 "$TARGET"/*

if [ "$RETENTION_DAYS" -ge 1 ] 2>/dev/null; then
  find "$BACKUP_ROOT" \
    -mindepth 1 \
    -maxdepth 1 \
    -type d \
    -mtime "+$RETENTION_DAYS" \
    -exec rm -rf -- {} +
fi

echo "Backup created: $TARGET"
