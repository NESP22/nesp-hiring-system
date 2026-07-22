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

# Keep the installer lock on persistent storage. Before installation, a dangling
# symlink lets the installer create the persistent target. After installation,
# use a real app-root file because protected symlink rules can make file_exists()
# return false for Apache in sticky directories.
rm -f "$APP_ROOT/INSTALL_BLOCK"
if [ -f "$DATA_ROOT/INSTALL_BLOCK" ]; then
  cp "$DATA_ROOT/INSTALL_BLOCK" "$APP_ROOT/INSTALL_BLOCK"
  chown www-data:www-data "$APP_ROOT/INSTALL_BLOCK"
else
  ln -s "$DATA_ROOT/INSTALL_BLOCK" "$APP_ROOT/INSTALL_BLOCK"
  chown -h www-data:www-data "$APP_ROOT/INSTALL_BLOCK"
fi

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

function updateDefine($config, $constant, $value)
{
    $replacement = "define('{$constant}', " . var_export($value, true) . ");";
    $pattern = "/define\\(\\s*['\"]" . preg_quote($constant, '/') . "['\"]\\s*,.*?\\);/";
    $updated = preg_replace($pattern, $replacement, $config, 1, $count);
    if ($updated === null || $count !== 1) {
        fwrite(STDERR, "Persistent OpenCATS mail configuration is incomplete.\n");
        exit(1);
    }

    return $updated;
}

function mailEnv($name)
{
    $value = getenv($name);
    return $value === false ? '' : trim((string) $value);
}

function failClosedMailConfiguration()
{
    // Do not include an environment value in startup output. Render logs are
    // operational telemetry, not a secret store.
    fwrite(STDERR, "OpenCATS mail is enabled but its required configuration is incomplete or invalid.\n");
    exit(1);
}

function updateMailerSettings($enabled, $fromAddress)
{
    $host = getenv('DB_HOST') ?: 'localhost';
    $user = getenv('DB_USER') ?: 'opencats';
    $password = getenv('DB_PASSWORD') ?: '';
    $database = getenv('DB_NAME') ?: 'cats';
    $port = (int) (getenv('DB_PORT') ?: 3306);

    mysqli_report(MYSQLI_REPORT_OFF);
    $connection = @new mysqli($host, $user, $password, $database, $port);
    if ($connection->connect_errno) {
        if (!$enabled) {
            return;
        }
        fwrite(STDERR, "Unable to apply the persistent OpenCATS mail safety settings.\n");
        exit(1);
    }

    $table = $connection->query("SHOW TABLES LIKE 'settings'");
    if ($table === false || $table->num_rows !== 1) {
        $connection->close();
        if (!$enabled) {
            return;
        }
        fwrite(STDERR, "Unable to apply the persistent OpenCATS mail safety settings.\n");
        exit(1);
    }
    $table->free();

    if (!$connection->begin_transaction()) {
        fwrite(STDERR, "Unable to start the persistent OpenCATS mail safety update.\n");
        exit(1);
    }

    $configured = $enabled ? '1' : '0';
    $fromAddress = $enabled ? $fromAddress : '';
    $settings = array(
        'fromAddress' => $fromAddress,
        'configured' => $configured
    );
    foreach ($settings as $setting => $value) {
        $delete = $connection->prepare('DELETE FROM settings WHERE setting = ? AND settings_type = 1');
        $insert = $connection->prepare('INSERT INTO settings (setting, value, settings_type) VALUES (?, ?, 1)');
        if ($delete === false || $insert === false
            || !$delete->bind_param('s', $setting)
            || !$delete->execute()
            || !$insert->bind_param('ss', $setting, $value)
            || !$insert->execute()) {
            $connection->rollback();
            fwrite(STDERR, "Unable to apply the persistent OpenCATS mail safety settings.\n");
            exit(1);
        }
        $delete->close();
        $insert->close();
    }

    if (!$connection->commit()) {
        $connection->rollback();
        fwrite(STDERR, "Unable to apply the persistent OpenCATS mail safety settings.\n");
        exit(1);
    }

    $connection->close();
}

$mailEnabled = mailEnv('OPENCATS_MAIL_ENABLED') === '1';
$mailValues = array(
    'MAIL_MAILER' => 0,
    'MAIL_SMTP_HOST' => '',
    'MAIL_SMTP_PORT' => 0,
    'MAIL_SMTP_AUTH' => false,
    'MAIL_SMTP_USER' => '',
    'MAIL_SMTP_PASS' => '',
    'MAIL_SMTP_SECURE' => ''
);
$fromAddress = '';

if ($mailEnabled) {
    $mailer = strtolower(mailEnv('OPENCATS_MAIL_MAILER'));
    $smtpHost = mailEnv('OPENCATS_MAIL_SMTP_HOST');
    $smtpPort = mailEnv('OPENCATS_MAIL_SMTP_PORT');
    $smtpAuth = mailEnv('OPENCATS_MAIL_SMTP_AUTH');
    $smtpUser = mailEnv('OPENCATS_MAIL_SMTP_USER');
    $smtpPass = mailEnv('OPENCATS_MAIL_SMTP_PASS');
    $smtpSecure = strtolower(mailEnv('OPENCATS_MAIL_SMTP_SECURE'));
    $fromAddress = mailEnv('OPENCATS_MAIL_FROM_ADDRESS');

    if ($mailer !== 'smtp'
        || $smtpHost === '' || strlen($smtpHost) > 255 || preg_match('/\\s/', $smtpHost)
        || !ctype_digit($smtpPort) || (int) $smtpPort < 1 || (int) $smtpPort > 65535
        || $smtpAuth !== '1'
        || $smtpUser === '' || $smtpPass === ''
        || !in_array($smtpSecure, array('tls', 'ssl'), true)
        || filter_var($fromAddress, FILTER_VALIDATE_EMAIL) === false) {
        failClosedMailConfiguration();
    }

    $mailValues = array(
        'MAIL_MAILER' => 3,
        'MAIL_SMTP_HOST' => $smtpHost,
        'MAIL_SMTP_PORT' => (int) $smtpPort,
        'MAIL_SMTP_AUTH' => true,
        'MAIL_SMTP_USER' => $smtpUser,
        'MAIL_SMTP_PASS' => $smtpPass,
        'MAIL_SMTP_SECURE' => $smtpSecure
    );
}

foreach ($mailValues as $constant => $value) {
    $config = updateDefine($config, $constant, $value);
}

if (file_put_contents($path, $config, LOCK_EX) === false) {
    fwrite(STDERR, "Unable to update persistent OpenCATS config.\n");
    exit(1);
}

// The web service owns persistent mail settings. Cron containers may load the
// same constants, but scheduled board imports suppress questionnaire delivery
// and must never rewrite the shared settings table during startup.
if (mailEnv('NESP_SERVICE_ROLE') !== 'cron') {
    updateMailerSettings($mailEnabled, $fromAddress);
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
<LocationMatch "^/(?!render-health\.txt$|modules/boardintake/missiveWebhook\.php$)">
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
