#!/bin/sh
set -eu

APP_ROOT=/var/www/html
DATA_ROOT=/var/data
PORT="${PORT:-10000}"

mkdir -p "$DATA_ROOT" "$DATA_ROOT/state"

# Render requires the web process to bind to its PORT value.
printf 'Listen %s\n' "$PORT" > /etc/apache2/ports.conf
sed "s/__PORT__/$PORT/g" \
  /etc/apache2/sites-available/000-default.conf.template \
  > /etc/apache2/sites-available/000-default.conf

persist_directory() {
  name="$1"
  source="$APP_ROOT/$name"
  target="$DATA_ROOT/$name"

  if [ ! -d "$target" ]; then
    mkdir -p "$target"
    if [ -d "$source" ]; then
      cp -a "$source"/. "$target"/ 2>/dev/null || true
    fi
  fi

  rm -rf "$source"
  ln -s "$target" "$source"
}

persist_directory attachments
persist_directory uploads
persist_directory temp

# The installer updates config.php. Keep it on the persistent disk so a
# redeploy does not erase the database and mail configuration.
if [ ! -f "$DATA_ROOT/config.php" ]; then
  cp "$APP_ROOT/config.php" "$DATA_ROOT/config.php"
fi
rm -f "$APP_ROOT/config.php"
ln -s "$DATA_ROOT/config.php" "$APP_ROOT/config.php"

# Keep the installer lock on persistent storage. A dangling symlink is
# intentional before installation; file_exists() remains false until the
# installer creates the target.
rm -f "$APP_ROOT/INSTALL_BLOCK"
ln -s "$DATA_ROOT/INSTALL_BLOCK" "$APP_ROOT/INSTALL_BLOCK"

# Use Render's private-service values in the persistent OpenCATS config.
# The installer can still validate and rewrite this file during first setup.
php <<'PHP'
<?php
$path = '/var/data/config.php';
$config = file_get_contents($path);
if ($config === false) {
    fwrite(STDERR, "Unable to read persistent OpenCATS config.\n");
    exit(1);
}

$values = [
    'DATABASE_USER' => getenv('DB_USER') ?: 'opencats',
    'DATABASE_PASS' => getenv('DB_PASSWORD') ?: '',
    'DATABASE_HOST' => getenv('DB_HOST') ?: 'localhost',
    'DATABASE_NAME' => getenv('DB_NAME') ?: 'cats',
];

foreach ($values as $constant => $value) {
    $replacement = "define('{$constant}', " . var_export($value, true) . ");";
    $pattern = "/define\\(\\s*['\"]" . preg_quote($constant, '/') . "['\"]\\s*,.*?\\);/";
    $config = preg_replace($pattern, $replacement, $config, 1);
}

if ((getenv('OPENCATS_MAIL_ENABLED') ?: '0') !== '1') {
    $config = preg_replace(
        "/define\\(\\s*['\"]MAIL_MAILER['\"]\\s*,.*?\\);/",
        "define('MAIL_MAILER', 0);",
        $config,
        1
    );
}

if (file_put_contents($path, $config, LOCK_EX) === false) {
    fwrite(STDERR, "Unable to update persistent OpenCATS config.\n");
    exit(1);
}
PHP

# Password-protect the full site during installation and private testing.
# The health check remains public so Render can verify the service.
AUTH_CONF=/etc/apache2/conf-enabled/nesp-install-auth.conf
if [ "${APP_BASIC_AUTH:-1}" = "1" ]; then
  : "${INSTALL_ACCESS_USER:?Set INSTALL_ACCESS_USER in Render}"
  : "${INSTALL_ACCESS_PASSWORD:?Set INSTALL_ACCESS_PASSWORD in Render}"
  htpasswd -bc "$DATA_ROOT/.htpasswd" \
    "$INSTALL_ACCESS_USER" "$INSTALL_ACCESS_PASSWORD" >/dev/null
  cat > "$AUTH_CONF" <<'APACHE'
<LocationMatch "^/(?!render-health\.txt$)">
    AuthType Basic
    AuthName "NESP Hiring System Setup"
    AuthUserFile /var/data/.htpasswd
    Require valid-user
</LocationMatch>
APACHE
else
  rm -f "$AUTH_CONF" "$DATA_ROOT/.htpasswd"
fi

chown -R www-data:www-data "$DATA_ROOT"
chmod 0750 "$DATA_ROOT"
[ ! -f "$DATA_ROOT/.htpasswd" ] || chmod 0640 "$DATA_ROOT/.htpasswd"

exec "$@"
