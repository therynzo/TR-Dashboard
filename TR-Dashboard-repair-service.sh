#!/usr/bin/env bash
# TR Dashboard service repair — preserve application files and database.
set -Eeuo pipefail
umask 027

APP_DIR="${1:-/var/www/tr-dashboard}"
SERVICE_USER="trdashboard"
SERVICE_NAME="tr-dashboard.service"

fail() { printf '[ERROR] %s\n' "$*" >&2; exit 1; }
info() { printf '[INFO] %s\n' "$*"; }
ok() { printf '[OK] %s\n' "$*"; }

[[ "$EUID" -eq 0 ]] || fail 'Run as root: sudo bash TR-Dashboard-repair-service.sh [/path/to/tr-dashboard].'
[[ "$APP_DIR" =~ ^/[A-Za-z0-9._/-]+$ && "$APP_DIR" != / ]] || fail 'Use a valid absolute application path without spaces.'
case "$APP_DIR" in */../*|*/..|*/./*|*/.) fail 'Application path must not contain . or .. path segments.' ;; esac
[[ -d "$APP_DIR" ]] || fail "Application directory not found: $APP_DIR. No changes made."
[[ -f "$APP_DIR/artisan" && -f "$APP_DIR/.env" ]] || fail "artisan or .env is missing in $APP_DIR; stopping without modifying anything."
[[ -f "$APP_DIR/vendor/autoload.php" ]] || fail 'vendor/autoload.php is missing. Run composer install from the existing app directory first; do not delete the database.'
id "$SERVICE_USER" >/dev/null 2>&1 || fail "Service user $SERVICE_USER does not exist. Inspect the installation first."
SERVICE_GROUP="$(id -gn "$SERVICE_USER")"

if ! command -v setfacl >/dev/null 2>&1; then
  if command -v apt-get >/dev/null 2>&1; then
    export DEBIAN_FRONTEND=noninteractive
    apt-get update -y
    apt-get install -y acl
  elif command -v dnf >/dev/null 2>&1; then
    dnf install -y acl
  else
    fail 'Install the acl package, then run this script again.'
  fi
fi

info 'Stopping the service while checking permissions...'
systemctl stop "$SERVICE_NAME" 2>/dev/null || true
info "Granting $SERVICE_USER path traversal and application read access..."
cursor="$APP_DIR"
while [[ "$cursor" != / ]]; do
  [[ -d "$cursor" ]] || fail "Missing directory in application path: $cursor"
  if [[ "$cursor" == "$APP_DIR" ]]; then
    setfacl -m "u:$SERVICE_USER:rx" "$cursor"
  else
    setfacl -m "u:$SERVICE_USER:--x" "$cursor"
  fi
  cursor="$(dirname -- "$cursor")"
done
setfacl -R -m "u:$SERVICE_USER:r-X" "$APP_DIR"

info 'Repairing Laravel runtime directory permissions...'
mkdir -p "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
chown -R "$SERVICE_USER:$SERVICE_GROUP" "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
chmod -R u+rwX,g+rwX,o-rwx "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
chown "root:$SERVICE_GROUP" "$APP_DIR/.env"
chmod 640 "$APP_DIR/.env"
setfacl -m "u:$SERVICE_USER:r--" "$APP_DIR/.env"
if grep -Eq '^DB_CONNECTION="?sqlite"?$' "$APP_DIR/.env" && [[ -f "$APP_DIR/database/database.sqlite" ]]; then
  setfacl -m "u:$SERVICE_USER:rwx" "$APP_DIR/database"
  chown "$SERVICE_USER:$SERVICE_GROUP" "$APP_DIR/database/database.sqlite"
  chmod 660 "$APP_DIR/database/database.sqlite"
fi

info 'Checking access to source, Composer autoloader, environment, and runtime paths...'
for entry in \
  "-x:$APP_DIR" \
  "-r:$APP_DIR/artisan" \
  "-r:$APP_DIR/vendor/autoload.php" \
  "-r:$APP_DIR/.env" \
  "-w:$APP_DIR/storage" \
  "-w:$APP_DIR/bootstrap/cache"; do
  perm="${entry%%:*}"
  path="${entry#*:}"
  runuser -u "$SERVICE_USER" -- test "$perm" "$path" || {
    namei -l "$path" >&2 2>/dev/null || true
    getfacl "$path" >&2 2>/dev/null || true
    fail "$SERVICE_USER lacks $perm permission for $path."
  }
done
if grep -Eq '^DB_CONNECTION="?sqlite"?$' "$APP_DIR/.env" && [[ -f "$APP_DIR/database/database.sqlite" ]]; then
  runuser -u "$SERVICE_USER" -- test -r "$APP_DIR/database/database.sqlite" || fail 'SQLite database is not readable by the service user.'
  runuser -u "$SERVICE_USER" -- test -w "$APP_DIR/database/database.sqlite" || fail 'SQLite database is not writable by the service user.'
fi
runuser -u "$SERVICE_USER" -- bash -c "cd '$APP_DIR' && /usr/bin/php artisan --version" || fail 'Laravel could not bootstrap as the service user; inspect .env, vendor and permissions.'
ok 'Directory, Composer, environment, and Laravel bootstrap checks passed.'

info 'Writing the systemd unit with the required port 1000 bind capability...'
cat > "/etc/systemd/system/$SERVICE_NAME" <<UNIT
[Unit]
Description=TR Dashboard PHP application server
After=network.target

[Service]
Type=simple
User=$SERVICE_USER
Group=$SERVICE_GROUP
WorkingDirectory=$APP_DIR
ExecStart=/usr/bin/php $APP_DIR/artisan serve --host=0.0.0.0 --port=1000
Restart=on-failure
RestartSec=3
Environment=APP_ENV=production
UMask=0027
CapabilityBoundingSet=CAP_NET_BIND_SERVICE
AmbientCapabilities=CAP_NET_BIND_SERVICE
NoNewPrivileges=true

[Install]
WantedBy=multi-user.target
UNIT
chmod 644 "/etc/systemd/system/$SERVICE_NAME"
systemctl daemon-reload
systemctl reset-failed "$SERVICE_NAME" 2>/dev/null || true
systemctl enable "$SERVICE_NAME" >/dev/null
systemctl restart "$SERVICE_NAME"

info 'Checking local HTTP health...'
code='000'
for _attempt in {1..15}; do
  code="$(curl -sS --max-time 3 -o /dev/null -w '%{http_code}' http://127.0.0.1:1000/ 2>/dev/null || true)"
  [[ "$code" =~ ^[23][0-9][0-9]$ ]] && break
  sleep 1
done
if [[ "$code" =~ ^[23][0-9][0-9]$ ]]; then
  ok "TR Dashboard responds with HTTP $code at http://127.0.0.1:1000/"
  systemctl --no-pager --full status "$SERVICE_NAME" || true
else
  printf '[ERROR] HTTP health check failed (received %s). Recent service logs:\n' "${code:-000}" >&2
  systemctl --no-pager --full status "$SERVICE_NAME" >&2 || true
  journalctl -u "$SERVICE_NAME" -n 80 --no-pager >&2 || true
  namei -l "$APP_DIR/vendor/autoload.php" >&2 || true
  getfacl "$APP_DIR/vendor/autoload.php" >&2 || true
  fail 'Repair did not pass the HTTP health check. No database was deleted or reset.'
fi
