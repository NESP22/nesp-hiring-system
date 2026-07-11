#!/usr/bin/env sh
set -eu

REPO_ROOT="$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd)"
COMPOSE_FILE="$REPO_ROOT/docker/docker-compose.production.yml"
ENV_FILE="$REPO_ROOT/docker/.env.production"
BACKUP_ROOT="${BACKUP_ROOT:-$REPO_ROOT/docker/backups}"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
TARGET="$BACKUP_ROOT/$STAMP"

if [ ! -f "$ENV_FILE" ]; then
  echo "Missing $ENV_FILE" >&2
  exit 1
fi

mkdir -p "$TARGET"

compose() {
  docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" "$@"
}

echo "Creating MariaDB dump..."
compose exec -T opencatsdb sh -c '
  MARIADB_PWD="$MARIADB_ROOT_PASSWORD" \
  mariadb-dump \
    --user=root \
    --single-transaction \
    --routines \
    --events \
    --triggers \
    "$MARIADB_DATABASE"
' > "$TARGET/database.sql"

echo "Archiving OpenCATS generated data..."
cd "$REPO_ROOT"

set --
for path in config.php INSTALL_BLOCK attachments uploads temp; do
  if [ -e "$path" ]; then
    set -- "$@" "$path"
  fi
done

if [ "$#" -gt 0 ]; then
  tar -czf "$TARGET/opencats-data.tar.gz" "$@"
else
  tar -czf "$TARGET/opencats-data.tar.gz" --files-from /dev/null
fi

cat > "$TARGET/README.txt" <<EOF
NESP Hiring System backup
Created: $STAMP UTC
Contents:
- database.sql
- opencats-data.tar.gz

This backup intentionally does not contain docker/.env.production.
Store this directory in an encrypted off-server backup location.
EOF

chmod -R go-rwx "$TARGET"

echo "Backup created at: $TARGET"
