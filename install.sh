#!/usr/bin/env bash
# TR Dashboard installer — Officially made by TheRynzo
# Compatible with Bash 4+; package names vary by distribution and are checked during installation.
set -Eeuo pipefail
umask 027

BLUE=$'\033[38;5;135m'
CYAN=$'\033[38;5;141m'
PURPLE=$'\033[38;5;177m'
WHITE=$'\033[1;37m'
RED=$'\033[0;31m'
GREEN=$'\033[0;32m'
RESET=$'\033[0m'
REPO_URL='https://github.com/therynzo/TR-Dashboard.git'
REPO_DIR="${TR_DASHBOARD_REPO_DIR:-/opt/TR-Dashboard}"
PACKAGE_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"

fail() {
  trap - ERR
  printf '%s[ERROR]%s %s\n' "$RED" "$RESET" "$*" >&2
  exit 1
}
info() { printf '%s[INFO]%s %s\n' "$CYAN" "$RESET" "$*"; }
ok() { printf '%s[OK]%s %s\n' "$GREEN" "$RESET" "$*"; }
warn() { printf '%s[WARN]%s %s\n' "$PURPLE" "$RESET" "$*" >&2; }
trap 'rc=$?; printf "\n%s[ERROR]%s Installer stopped at line %s (exit %s). Review the message above.\n" "$RED" "$RESET" "$LINENO" "$rc" >&2; exit "$rc"' ERR

banner() {
  if [[ -t 1 ]]; then clear 2>/dev/null || true; fi
  printf '%s  ████████╗██████╗     %s██████╗  █████╗ ███████╗██╗  ██╗%s\n' "$BLUE" "$CYAN" "$RESET"
  printf '%s  ╚══██╔══╝██╔══██╗    %s██╔══██╗██╔══██╗██╔════╝██║  ██║%s\n' "$BLUE" "$CYAN" "$RESET"
  printf '%s     ██║   ██████╔╝    %s██║  ██║███████║███████╗███████║%s\n' "$BLUE" "$CYAN" "$RESET"
  printf '%s     ██║   ██╔══██╗    %s██║  ██║██╔══██║╚════██║██╔══██║%s\n' "$BLUE" "$CYAN" "$RESET"
  printf '%s     ██║   ██║  ██║    %s██████╔╝██║  ██║███████║██║  ██║%s\n' "$BLUE" "$CYAN" "$RESET"
  printf '%s     ╚═╝   ╚═╝  ╚═╝    %s╚═════╝ ╚═╝  ╚═╝╚══════╝╚═╝  ╚═╝%s\n' "$BLUE" "$CYAN" "$RESET"
  printf '\n%s             T R   D A S H B O A R D%s\n' "$PURPLE" "$RESET"
  printf '%s                 T H E R Y N Z O%s\n' "$CYAN" "$RESET"
  printf '%s       VPS Installer • Officially made by TheRynzo%s\n\n' "$WHITE" "$RESET"
}

# Non-installing validation option. Useful when checking a downloaded script.
if [[ "${1:-}" == '--check' ]]; then
  bash -n "$0"
  ok 'Bash syntax check passed.'
  if [[ -d "$PACKAGE_DIR/payload" ]]; then
    [[ -f "$PACKAGE_DIR/payload/routes/web.php" ]] || fail 'payload/routes/web.php is missing.'
    [[ -f "$PACKAGE_DIR/payload/app/Console/Commands/BootstrapTrDashboard.php" ]] || fail 'TR Dashboard bootstrap command is missing.'
    ok 'Required payload files are present.'
  else
    info 'This is a standalone copy of install.sh; payload checks run after the repository is cloned.'
  fi
  exit 0
fi

# A script fetched as a single raw GitHub file has no sibling payload. Clone
# the full repo first, then execute the repository's complete installer.
if [[ ! -d "$PACKAGE_DIR/payload" ]]; then
  banner
  info 'Standalone installer detected; fetching the full GitHub repository first.'
  [[ "$EUID" -eq 0 ]] || fail 'Run with sudo: sudo bash install.sh. Root access is needed to install system packages.'
  if ! command -v git >/dev/null 2>&1; then
    if command -v apt-get >/dev/null 2>&1; then
      export DEBIAN_FRONTEND=noninteractive
      apt-get update -y
      apt-get install -y git ca-certificates curl
    elif command -v dnf >/dev/null 2>&1; then
      dnf install -y git ca-certificates curl
    else
      fail 'Git is missing and no supported apt-get/dnf package manager was found.'
    fi
  fi

  if [[ -d "$REPO_DIR/.git" ]]; then
    REMOTE_URL="$(git -C "$REPO_DIR" remote get-url origin 2>/dev/null || true)"
    case "${REMOTE_URL%.git}" in
      'https://github.com/therynzo/TR-Dashboard'|'git@github.com:therynzo/TR-Dashboard') ;;
      *) fail "Repository directory $REPO_DIR is already a Git checkout with an unexpected origin: ${REMOTE_URL:-not set}. Choose another path with TR_DASHBOARD_REPO_DIR=/opt/TR-Dashboard-fresh." ;;
    esac
    if [[ -n "$(git -C "$REPO_DIR" status --porcelain 2>/dev/null || true)" ]]; then
      fail "Local changes exist in $REPO_DIR. Back them up or choose a fresh path with TR_DASHBOARD_REPO_DIR=/opt/TR-Dashboard-fresh."
    fi
    git -C "$REPO_DIR" fetch --depth 1 origin main
    # GitHub main may have been force-updated, leaving no common ancestor.
    # The worktree was verified clean above and the origin was verified, so
    # point the local main branch at the fetched official commit without merging.
    git -C "$REPO_DIR" checkout -B main FETCH_HEAD
  else
    if [[ -e "$REPO_DIR" && -n "$(find "$REPO_DIR" -mindepth 1 -maxdepth 1 -print -quit 2>/dev/null || true)" ]]; then
      fail "Repository target is not empty: $REPO_DIR. Choose another path with TR_DASHBOARD_REPO_DIR=/opt/TR-Dashboard-fresh."
    fi
    mkdir -p "$(dirname -- "$REPO_DIR")"
    rmdir "$REPO_DIR" 2>/dev/null || true
    git clone --depth 1 --branch main "$REPO_URL" "$REPO_DIR"
  fi
  [[ -f "$REPO_DIR/install.sh" && -d "$REPO_DIR/payload" ]] || fail 'Repository is missing install.sh or payload/. Ensure the complete package is committed to GitHub main.'
  exec bash "$REPO_DIR/install.sh" "$@"
fi

# Validate package before asking for credentials or making system changes.
[[ -d "$PACKAGE_DIR/payload" ]] || fail "Missing payload directory beside installer: $PACKAGE_DIR/payload"
[[ -f "$PACKAGE_DIR/payload/routes/web.php" ]] || fail 'payload/routes/web.php is missing.'
[[ -f "$PACKAGE_DIR/payload/app/Console/Commands/BootstrapTrDashboard.php" ]] || fail 'TR Dashboard bootstrap command is missing from payload.'
[[ -f "$PACKAGE_DIR/payload/database/migrations/2026_10_23_000001_add_tr_dashboard_fields_to_users.php" ]] || fail 'Required TR Dashboard database migrations are missing.'
[[ -f "$PACKAGE_DIR/payload/app/Http/Controllers/AdminSettingsController.php" ]] || fail 'Separated admin settings controller is missing.'
[[ -f "$PACKAGE_DIR/payload/app/Http/Controllers/OAuthController.php" ]] || fail 'OAuth controller is missing.'
[[ -f "$PACKAGE_DIR/payload/app/Http/Controllers/ProfileController.php" ]] || fail 'Profile controller is missing.'
[[ -f "$PACKAGE_DIR/payload/app/Http/Controllers/LinkvertiseController.php" ]] || fail 'Linkvertise controller is missing.'
[[ -f "$PACKAGE_DIR/payload/database/migrations/2026_10_23_000004_add_social_auth_fields_to_users.php" ]] || fail 'Social login migration is missing.'
[[ -f "$PACKAGE_DIR/payload/database/migrations/2026_10_23_000005_add_local_password_flag_to_users.php" ]] || fail 'Local password migration is missing.'
[[ -f "$PACKAGE_DIR/payload/database/migrations/2026_10_23_000006_create_linkvertise_reward_claims_table.php" ]] || fail 'Linkvertise replay-protection migration is missing.'
[[ -f "$PACKAGE_DIR/payload/resources/views/admin/settings/pterodactyl.blade.php" ]] || fail 'Separate Pterodactyl settings view is missing.'
[[ -f "$PACKAGE_DIR/payload/resources/views/admin/settings/authentication.blade.php" ]] || fail 'Separate authentication settings view is missing.'
[[ -f "$PACKAGE_DIR/payload/resources/views/admin/settings/linkvertise.blade.php" ]] || fail 'Separate Linkvertise settings view is missing.'
[[ -f "$PACKAGE_DIR/payload/public/tr-logo.png" ]] || fail 'The dashboard logo is missing.'
[[ "$EUID" -eq 0 ]] || fail 'Run as root: sudo ./install.sh. The installer needs to install packages and register system services.'

# Shared repair/update helpers used by menu actions 2–4.
choose_app_dir() {
  local default_dir="${1:-/var/www/tr-dashboard}"
  read -r -p "Laravel application directory [${default_dir}]: " INPUT_APP_DIR
  APP_DIR="${INPUT_APP_DIR:-$default_dir}"
  APP_DIR="${APP_DIR%/}"
  [[ "$APP_DIR" =~ ^/[A-Za-z0-9._/-]+$ && "$APP_DIR" != / ]] || fail 'Enter a valid absolute application path without spaces.'
  case "$APP_DIR" in */../*|*/..|*/./*|*/.) fail 'Application path must not contain . or .. path segments.' ;; esac
  [[ ! -L "$APP_DIR" ]] || fail 'Application directory must not be a symbolic link.'
}

ensure_acl() {
  if ! command -v setfacl >/dev/null 2>&1; then
    export DEBIAN_FRONTEND=noninteractive
    if command -v apt-get >/dev/null 2>&1; then apt-get update -y && apt-get install -y acl
    elif command -v dnf >/dev/null 2>&1; then dnf install -y acl
    else fail 'The setfacl utility is required, and no apt-get/dnf package manager was found.'; fi
  fi
}

backup_app() {
  local target="$1" label="${2:-backup}" stamp backup
  stamp="$(date +%Y%m%d-%H%M%S)"
  mkdir -p /root/tr-dashboard-backups
  backup="/root/tr-dashboard-backups/tr-dashboard-${label}-${stamp}.tar.gz"
  info "Creating a safety backup at $backup..."
  tar -czf "$backup" --exclude='./vendor' --exclude='./storage/logs' --exclude='./bootstrap/cache' -C "$target" .
  [[ -s "$backup" ]] || fail 'Backup archive was not created successfully.'
  ok "Backup created: $backup"
}

set_app_permissions() {
  local target="$1" svc='trdashboard' svc_group cursor
  [[ -d "$target" && -f "$target/artisan" && -f "$target/.env" ]] || fail "Not a Laravel installation: $target"
  if ! id "$svc" >/dev/null 2>&1; then useradd --system --user-group --home-dir "$target" --shell /usr/sbin/nologin "$svc"; fi
  svc_group="$(id -gn "$svc")"
  ensure_acl
  cursor="$(dirname -- "$target")"
  while [[ "$cursor" != / ]]; do
    setfacl -m "u:${svc}:--x" "$cursor" || fail "Could not grant path traversal through $cursor."
    cursor="$(dirname -- "$cursor")"
  done
  setfacl -m "u:${svc}:rx" "$target"
  setfacl -R -m "u:${svc}:r-X" "$target"
  mkdir -p "$target/storage" "$target/bootstrap/cache"
  chown -R "$svc:$svc_group" "$target/storage" "$target/bootstrap/cache"
  chmod -R u+rwX,go-rwx "$target/storage" "$target/bootstrap/cache"
  chown "root:$svc_group" "$target/.env"
  chmod 640 "$target/.env"
  setfacl -m "u:${svc}:r--" "$target/.env"
  if grep -Eq '^DB_CONNECTION="?sqlite"?$' "$target/.env" && [[ -f "$target/database/database.sqlite" ]]; then
    setfacl -m "u:${svc}:rwx" "$target/database"
    chown "$svc:$svc_group" "$target/database/database.sqlite"
    chmod 660 "$target/database/database.sqlite"
  fi
  runuser -u "$svc" -- test -x "$target" || fail "Service user $svc cannot enter $target."
  runuser -u "$svc" -- test -r "$target/artisan" || fail "Service user $svc cannot read artisan."
  runuser -u "$svc" -- test -r "$target/vendor/autoload.php" || fail "Composer autoloader is not readable. Run composer install."
  runuser -u "$svc" -- test -r "$target/.env" || fail "Service user $svc cannot read .env."
  runuser -u "$svc" -- test -w "$target/storage" || fail 'Laravel storage is not writable by the service user.'
  runuser -u "$svc" -- test -w "$target/bootstrap/cache" || fail 'Laravel bootstrap/cache is not writable by the service user.'
}

write_service_unit() {
  local target="$1" php_bin
  php_bin="$(command -v php)"
  local service_group
  service_group="$(id -gn trdashboard)"
  cat > /etc/systemd/system/tr-dashboard.service <<UNIT
[Unit]
Description=TR Dashboard PHP application server
After=network.target

[Service]
Type=simple
User=trdashboard
Group=$service_group
WorkingDirectory=$target
ExecStart=$php_bin $target/artisan serve --host=0.0.0.0 --port=1000
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
  chmod 644 /etc/systemd/system/tr-dashboard.service
  systemctl daemon-reload
  systemctl enable tr-dashboard.service >/dev/null
}

restart_and_check() {
  local target="$1" status='000' attempt
  set_app_permissions "$target"
  write_service_unit "$target"
  info 'Checking Laravel bootstrap as the service user...'
  runuser -u trdashboard -- bash -c "cd '$target' && /usr/bin/php artisan --version" || fail 'Laravel could not bootstrap as the service user; check file permissions and .env.'
  systemctl reset-failed tr-dashboard.service 2>/dev/null || true
  systemctl restart tr-dashboard.service
  for attempt in {1..15}; do
    status="$(curl -sS --max-time 3 -o /dev/null -w '%{http_code}' http://127.0.0.1:1000/ 2>/dev/null || true)"
    [[ "$status" =~ ^[23][0-9][0-9]$ ]] && break
    sleep 1
  done
  if [[ ! "$status" =~ ^[23][0-9][0-9]$ ]]; then
    journalctl -u tr-dashboard.service -n 60 --no-pager >&2 || true
    fail "Dashboard health check failed (HTTP ${status:-000}). App files and database remain in place."
  fi
  ok "TR Dashboard is running (HTTP $status) at http://127.0.0.1:1000."
  systemctl --no-pager --full status tr-dashboard.service || true
}

run_update() {
  local target="$1"
  [[ -d "$target" && -f "$target/artisan" && -f "$target/.env" ]] || fail "No existing TR Dashboard Laravel installation found at $target. Install it first with option 1."
  [[ -d "$PACKAGE_DIR/payload" ]] || fail 'The repository payload is missing; cannot update the application.'
  command -v composer >/dev/null 2>&1 || fail 'Composer is missing. Install Composer before updating.'
  command -v php >/dev/null 2>&1 || fail 'PHP is missing.'
  php -r 'exit(version_compare(PHP_VERSION, "8.2.0", ">=") ? 0 : 1);' || fail 'PHP 8.2 or newer is required.'
  backup_app "$target" 'pre-update'
  info 'Applying updated TR Dashboard source files...'
  cp -a "$PACKAGE_DIR/payload/." "$target/"
  # Remove views/routes that were retired by the separated-settings and Credits UI.
  # A backup has already been created, and no user data lives in these view files.
  rm -f "$target/resources/views/dashboard/store.blade.php" \
        "$target/resources/views/admin/settings.blade.php"
  cd "$target"
  info 'Installing/updating Composer dependencies...'
  export COMPOSER_ALLOW_SUPERUSER=1
  composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-progress
  info 'Checking PHP syntax...'
  find app routes database/migrations resources/views -type f \( -name '*.php' -o -name '*.blade.php' \) -print0 | xargs -0 -r -n1 php -l
  php artisan migrate --force
  php artisan tr:bootstrap
  php artisan storage:link >/dev/null 2>&1 || true
  php artisan optimize:clear
  php artisan route:list --except-vendor >/dev/null
  ok 'Database migrations and Laravel routes are valid.'
  restart_and_check "$target"
  printf '\n%sUpdate completed. Your .env and database were preserved.%s\n' "$GREEN" "$RESET"
}

run_create_admin() {
  local target="$1"
  [[ -d "$target" && -f "$target/artisan" && -f "$target/.env" ]] || fail "No Laravel installation found at $target."
  set_app_permissions "$target"
  info 'Starting the secure interactive administrator creation command...'
  (cd "$target" && runuser -u trdashboard -- /usr/bin/php artisan tr:admin-create)
  ok 'Administrator creation command finished.'
}

run_delete_panel() {
  local target="$1" confirm repo_origin
  [[ -d "$target" && -f "$target/artisan" ]] || fail "No Laravel installation found at $target."
  printf '\n%sWARNING: This removes the installed TR Dashboard directory, service, and scheduler.%s\n' "$RED" "$RESET"
  printf 'A compressed backup will be saved under /root/tr-dashboard-backups.\n'
  read -r -p 'Continue with backup and removal? [y/N]: ' confirm
  [[ "$confirm" =~ ^[Yy]$ ]] || { echo 'Cancelled; nothing was removed.'; return 0; }
  backup_app "$target" 'before-delete'
  read -r -p 'Type DELETE TR DASHBOARD to permanently remove the application files: ' confirm
  [[ "$confirm" == 'DELETE TR DASHBOARD' ]] || { echo 'Confirmation did not match. Nothing was removed.'; return 0; }
  systemctl disable --now tr-dashboard.service 2>/dev/null || true
  rm -f /etc/systemd/system/tr-dashboard.service /etc/cron.d/tr-dashboard
  systemctl daemon-reload 2>/dev/null || true
  rm -rf -- "$target"
  # Remove ACL entries this installer added to the parent path.
  local cursor svc='trdashboard'
  cursor="$(dirname -- "$target")"
  while [[ "$cursor" != / ]]; do
    setfacl -x "u:$svc" "$cursor" 2>/dev/null || true
    cursor="$(dirname -- "$cursor")"
  done
  # Remove the installer checkout only when it is the expected verified project.
  if [[ -d "$REPO_DIR/.git" ]]; then
    repo_origin="$(git -C "$REPO_DIR" remote get-url origin 2>/dev/null || true)"
    case "${repo_origin%.git}" in
      'https://github.com/therynzo/TR-Dashboard'|'git@github.com:therynzo/TR-Dashboard')
        [[ "$REPO_DIR" == /opt/TR-Dashboard || "$REPO_DIR" == "$PACKAGE_DIR" ]] && rm -rf -- "$REPO_DIR" ;;
    esac
  fi
  if id "$svc" >/dev/null 2>&1; then
    userdel "$svc" 2>/dev/null || warn "Could not remove the $svc service account. Remove it manually only after checking it is unused."
  fi
  ok 'TR Dashboard application, service, scheduler, path ACLs, and dedicated service account were removed. A backup remains in /root/tr-dashboard-backups.'
  warn 'For safety, external Pterodactyl servers and external MySQL/MariaDB databases are not deleted automatically.'
}

banner
printf '  %s1)%s Install TR Dashboard\n  %s2)%s Update existing dashboard\n  %s3)%s Create an additional admin\n  %s4)%s Delete TR Dashboard\n  %s5)%s Exit\n\n' "$WHITE" "$RESET" "$WHITE" "$RESET" "$WHITE" "$RESET" "$WHITE" "$RESET" "$WHITE" "$RESET"
read -r -p 'Choose an option [1-5]: ' MENU_CHOICE
case "$MENU_CHOICE" in
  1) ;;
  2) choose_app_dir; run_update "$APP_DIR"; exit 0 ;;
  3) choose_app_dir; run_create_admin "$APP_DIR"; exit 0 ;;
  4) choose_app_dir; run_delete_panel "$APP_DIR"; exit 0 ;;
  5) echo 'Goodbye.'; exit 0 ;;
  *) fail 'Choose an option from 1 to 5.' ;;
esac

DEFAULT_URL='http://localhost:1000'
DEFAULT_DIR='/var/www/tr-dashboard'
read -r -p "Dashboard URL [${DEFAULT_URL}]: " DASH_URL
DASH_URL="${DASH_URL:-$DEFAULT_URL}"
[[ "$DASH_URL" =~ ^https?://[^[:space:]]+$ ]] || fail 'Dashboard URL must start with http:// or https:// (example: https://dash.example.com).'
DASH_URL="${DASH_URL%/}"
read -r -p "Install directory [${DEFAULT_DIR}]: " INSTALL_DIR
INSTALL_DIR="${INSTALL_DIR:-$DEFAULT_DIR}"
INSTALL_DIR="${INSTALL_DIR%/}"
[[ "$INSTALL_DIR" =~ ^/[A-Za-z0-9._/-]+$ ]] || fail 'Install path must be an absolute path without spaces or special characters.'
[[ "$INSTALL_DIR" != '/' ]] || fail 'Refusing to install into /. '
case "$INSTALL_DIR" in */../*|*/..|*/./*|*/.) fail 'Install path must not contain . or .. path segments.' ;; esac
[[ ! -L "$INSTALL_DIR" ]] || fail 'Install directory must not be a symbolic link.'
[[ ! -e "$INSTALL_DIR" || -d "$INSTALL_DIR" ]] || fail 'Install path already exists and is not a directory. Choose another path.'
if [[ -e "$INSTALL_DIR" && -n "$(find "$INSTALL_DIR" -mindepth 1 -maxdepth 1 -print -quit 2>/dev/null || true)" ]]; then
  fail "Install directory already exists and is not empty: $INSTALL_DIR. Choose a new directory to protect existing files."
fi

# Avoid overwriting or colliding with another service on the requested port.
if command -v ss >/dev/null 2>&1 && ss -ltnH 2>/dev/null | awk '$4 ~ /:1000$/ {found=1} END {exit !found}'; then
  fail 'TCP port 1000 is already listening. Stop the existing service or configure it to use another port before installing.'
fi

printf '\n%sDatabase setup%s\n' "$WHITE" "$RESET"
printf '  1) SQLite (recommended for a quick first install)\n  2) MySQL / MariaDB\n'
read -r -p 'Choose database [1]: ' DB_CHOICE
DB_CHOICE="${DB_CHOICE:-1}"
DB_NAME=''; DB_USER=''; DB_PASS=''; DB_HOST='127.0.0.1'; DB_PORT='3306'
if [[ "$DB_CHOICE" == '2' ]]; then
  read -r -p 'Database name: ' DB_NAME
  read -r -p 'Database username: ' DB_USER
  read -r -s -p 'Database password (input hidden; may be blank): ' DB_PASS; printf '\n'
  read -r -p "Database host [${DB_HOST}]: " DB_HOST; DB_HOST="${DB_HOST:-127.0.0.1}"
  read -r -p "Database port [${DB_PORT}]: " DB_PORT; DB_PORT="${DB_PORT:-3306}"
  [[ "$DB_NAME" =~ ^[A-Za-z0-9_]+$ ]] || fail 'Database name must contain only letters, numbers, or underscores.'
  [[ "$DB_USER" =~ ^[A-Za-z0-9_]+$ ]] || fail 'Database username must contain only letters, numbers, or underscores.'
  [[ "$DB_HOST" =~ ^[A-Za-z0-9._:-]+$ ]] || fail 'Database host contains unsupported characters.'
  [[ "$DB_PORT" =~ ^[0-9]{1,5}$ ]] && (( DB_PORT >= 1 && DB_PORT <= 65535 )) || fail 'Database port must be a number between 1 and 65535.'
elif [[ "$DB_CHOICE" != '1' ]]; then
  fail 'Choose database option 1 or 2.'
fi

printf '\n%sCreate the first administrator%s\n' "$WHITE" "$RESET"
read -r -p 'Admin email: ' ADMIN_EMAIL
read -r -p 'Admin username: ' ADMIN_USERNAME
[[ "$ADMIN_EMAIL" =~ ^[^[:space:]@]+@[^[:space:]@]+\.[^[:space:]@]+$ ]] || fail 'Enter a valid email address.'
[[ "$ADMIN_USERNAME" =~ ^[A-Za-z0-9_]{3,32}$ ]] || fail 'Username must be 3–32 characters using letters, numbers, or underscores.'
while true; do
  read -r -s -p 'Admin password (at least 8 characters; input hidden): ' ADMIN_PASSWORD; printf '\n'
  [[ ${#ADMIN_PASSWORD} -ge 8 ]] || { printf '%sPassword must be at least 8 characters.%s\n' "$RED" "$RESET"; continue; }
  read -r -s -p 'Confirm admin password: ' ADMIN_PASSWORD_CONFIRM; printf '\n'
  [[ "$ADMIN_PASSWORD" == "$ADMIN_PASSWORD_CONFIRM" ]] && break
  printf '%sPasswords do not match. Try again.%s\n' "$RED" "$RESET"
done

printf '\n%sInstallation summary%s\n' "$WHITE" "$RESET"
printf '  Dashboard URL : %s\n  Install path  : %s\n  Database      : %s\n  Admin email   : %s\n  Admin username: %s\n  App port      : 1000\n\n' \
  "$DASH_URL" "$INSTALL_DIR" "$([[ "$DB_CHOICE" == '2' ]] && echo 'MySQL/MariaDB' || echo 'SQLite')" \
  "$ADMIN_EMAIL" "$ADMIN_USERNAME"
read -r -p 'Proceed with installation? [Y/n]: ' CONFIRM
CONFIRM="${CONFIRM:-Y}"
[[ "$CONFIRM" =~ ^[Yy]$ ]] || { echo 'Installation cancelled.'; exit 0; }

install_apt_dependencies() {
  export DEBIAN_FRONTEND=noninteractive
  info 'Installing required packages with apt...'
  apt-get update -y
  apt-get install -y ca-certificates curl git unzip python3 software-properties-common cron acl \
    php-cli php-mbstring php-xml php-curl php-zip php-sqlite3 php-mysql composer

  if ! php -r 'exit(version_compare(PHP_VERSION, "8.2.0", ">=") ? 0 : 1);'; then
    . /etc/os-release
    if [[ "${ID:-}" == 'ubuntu' ]]; then
      info 'PHP 8.2+ is required; enabling the maintained Ondřej PHP PPA for Ubuntu...'
      command -v add-apt-repository >/dev/null 2>&1 || apt-get install -y software-properties-common
      add-apt-repository -y ppa:ondrej/php
      apt-get update -y
      apt-get install -y php-cli php-mbstring php-xml php-curl php-zip php-sqlite3 php-mysql composer
    else
      fail "Installed PHP is too old ($(php -r 'echo PHP_VERSION;')). PHP 8.2+ is required. On Debian, upgrade to a release whose default PHP is 8.2+ and rerun."
    fi
  fi
}

install_dnf_dependencies() {
  info 'Installing required packages with dnf...'
  dnf install -y ca-certificates curl git unzip python3 epel-release acl || true
  dnf install -y php-cli php-mbstring php-xml php-pdo php-mysqlnd php-sqlite3 php-curl php-zip composer cronie
  if ! php -r 'exit(version_compare(PHP_VERSION, "8.2.0", ">=") ? 0 : 1);'; then
    info 'Default PHP is older than 8.2; attempting the PHP 8.2 AppStream module...'
    dnf module reset php -y
    dnf module enable php:8.2 -y
    dnf install -y php-cli php-mbstring php-xml php-pdo php-mysqlnd php-sqlite3 php-curl php-zip composer
  fi
  systemctl enable --now crond 2>/dev/null || true
}

install_dependencies() {
  if command -v apt-get >/dev/null 2>&1; then
    install_apt_dependencies
  elif command -v dnf >/dev/null 2>&1; then
    install_dnf_dependencies
  else
    fail 'Unsupported operating system: apt-get or dnf is required. Install PHP 8.2+, Composer, Python 3, Git, and cron manually.'
  fi

  command -v php >/dev/null 2>&1 || fail 'PHP was not installed.'
  command -v composer >/dev/null 2>&1 || fail 'Composer was not installed.'
  command -v python3 >/dev/null 2>&1 || fail 'Python 3 is required for safe .env updates.'
  php -r 'exit(version_compare(PHP_VERSION, "8.2.0", ">=") ? 0 : 1);' || fail "PHP 8.2 or newer is required. Current version: $(php -r 'echo PHP_VERSION;')"
  php -r 'exit(extension_loaded("mbstring") && extension_loaded("openssl") && extension_loaded("pdo") && extension_loaded("xml") && extension_loaded("ctype") && extension_loaded("curl") && extension_loaded("fileinfo") && extension_loaded("zip") ? 0 : 1);' || fail 'Required PHP extensions are missing: mbstring, openssl, PDO, XML, ctype, cURL, fileinfo, and zip.'
  if [[ "$DB_CHOICE" == '1' ]]; then
    php -r 'exit(extension_loaded("pdo_sqlite") ? 0 : 1);' || fail 'pdo_sqlite is required for SQLite. Install the matching PHP SQLite extension and rerun.'
  else
    php -r 'exit(extension_loaded("pdo_mysql") ? 0 : 1);' || fail 'pdo_mysql is required for MySQL/MariaDB. Install the matching PHP MySQL extension and rerun.'
  fi
  composer --version >/dev/null 2>&1 || fail 'Composer did not start correctly.'
  ok "Dependencies are ready (PHP $(php -r 'echo PHP_VERSION;'))."
}

set_env_value() {
  local key="$1" value="$2"
  TR_ENV_FILE="$INSTALL_DIR/.env" TR_ENV_KEY="$key" TR_ENV_VALUE="$value" python3 - <<'PYENV'
import os
import re
path = os.environ['TR_ENV_FILE']
key = os.environ['TR_ENV_KEY']
value = os.environ['TR_ENV_VALUE'].replace('\r', '').replace('\n', '')
value = value.replace('\\', '\\\\').replace('"', '\\"').replace('$', '\\$')
# Keep dotenv boolean/null literals unquoted so Laravel casts APP_DEBUG=false correctly.
if value in {'true', 'false', 'null', 'empty'}:
    line = f'{key}={value}'
else:
    line = f'{key}="{value}"'
with open(path, 'r', encoding='utf-8') as handle:
    content = handle.read()
pattern = re.compile(r'^' + re.escape(key) + r'=.*$', re.MULTILINE)
if pattern.search(content):
    content = pattern.sub(lambda _match: line, content, count=1)
else:
    content = content.rstrip() + '\n' + line + '\n'
with open(path, 'w', encoding='utf-8') as handle:
    handle.write(content)
PYENV
}

install_dependencies
info "Creating a clean Laravel 12 application in $INSTALL_DIR..."
mkdir -p "$(dirname -- "$INSTALL_DIR")"
# Composer expects the project destination to be absent, not just empty.
if [[ -d "$INSTALL_DIR" ]]; then rmdir "$INSTALL_DIR"; fi
export COMPOSER_ALLOW_SUPERUSER=1
composer create-project laravel/laravel "$INSTALL_DIR" '^12.0' --no-interaction --prefer-dist --no-progress

info 'Copying TR Dashboard PHP source, views, routes, migrations, and logo...'
cp -a "$PACKAGE_DIR/payload/." "$INSTALL_DIR/"
cd "$INSTALL_DIR"
[[ -f artisan && -f app/Console/Commands/BootstrapTrDashboard.php && -f routes/web.php ]] || fail 'Laravel or TR Dashboard source files are missing after copying the payload.'
info 'Checking PHP source syntax before configuring the database...'
if ! find app routes database/migrations resources/views -type f \( -name '*.php' -o -name '*.blade.php' \) -print0 | xargs -0 -r -n1 php -l; then
  fail 'A PHP source file failed syntax validation. Review the file reported above before retrying.'
fi

set_env_value APP_NAME 'TR Dashboard'
set_env_value APP_ENV 'production'
set_env_value APP_DEBUG 'false'
set_env_value APP_URL "$DASH_URL"
set_env_value SESSION_DRIVER 'file'
set_env_value CACHE_STORE 'file'
set_env_value QUEUE_CONNECTION 'sync'

if [[ "$DB_CHOICE" == '2' ]]; then
  set_env_value DB_CONNECTION 'mysql'
  set_env_value DB_HOST "$DB_HOST"
  set_env_value DB_PORT "$DB_PORT"
  set_env_value DB_DATABASE "$DB_NAME"
  set_env_value DB_USERNAME "$DB_USER"
  set_env_value DB_PASSWORD "$DB_PASS"
else
  mkdir -p "$INSTALL_DIR/database"
  touch "$INSTALL_DIR/database/database.sqlite"
  set_env_value DB_CONNECTION 'sqlite'
  set_env_value DB_DATABASE "$INSTALL_DIR/database/database.sqlite"
fi

info 'Generating the Laravel key and running database migrations...'
php artisan key:generate --force
php artisan optimize:clear
php artisan migrate --force

export TR_BOOTSTRAP_ADMIN_EMAIL="$ADMIN_EMAIL"
export TR_BOOTSTRAP_ADMIN_USERNAME="$ADMIN_USERNAME"
export TR_BOOTSTRAP_ADMIN_PASSWORD="$ADMIN_PASSWORD"
php artisan tr:bootstrap
php artisan route:list --except-vendor >/dev/null
ok 'Laravel routes loaded successfully.'
unset TR_BOOTSTRAP_ADMIN_EMAIL TR_BOOTSTRAP_ADMIN_USERNAME TR_BOOTSTRAP_ADMIN_PASSWORD
unset ADMIN_PASSWORD ADMIN_PASSWORD_CONFIRM DB_PASS
php artisan storage:link >/dev/null 2>&1 || warn 'Storage symlink could not be created; it can be created later with php artisan storage:link.'
php artisan optimize:clear

# Create a dedicated service identity. Source is readable/traversable by the
# service but only Laravel runtime/cache and SQLite data are writable.
SERVICE_USER='trdashboard'
if ! id "$SERVICE_USER" >/dev/null 2>&1; then
  useradd --system --user-group --home-dir "$INSTALL_DIR" --shell /usr/sbin/nologin "$SERVICE_USER"
fi
SERVICE_GROUP="$(id -gn "$SERVICE_USER")"

# ACLs are required because umask 027 can leave root-owned Laravel directories
# (notably bootstrap/) inaccessible to the service. Grant traversal through
# parents, read/traverse in the Laravel tree, then write access only in runtime
# directories and the SQLite database.
if ! command -v setfacl >/dev/null 2>&1; then
  if command -v apt-get >/dev/null 2>&1; then
    DEBIAN_FRONTEND=noninteractive apt-get install -y acl
  elif command -v dnf >/dev/null 2>&1; then
    dnf install -y acl
  else
    fail 'The acl utility is required to grant the service user safe path access.'
  fi
fi
current_path="$INSTALL_DIR"
while [[ "$current_path" != "/" ]]; do
  if [[ "$current_path" == "$INSTALL_DIR" ]]; then
    setfacl -m "u:$SERVICE_USER:rx" "$current_path" || fail "Could not grant $SERVICE_USER access to $current_path."
  else
    setfacl -m "u:$SERVICE_USER:--x" "$current_path" || fail "Could not grant $SERVICE_USER path traversal through $current_path."
  fi
  current_path="$(dirname -- "$current_path")"
done
setfacl -R -m "u:$SERVICE_USER:r-X" "$INSTALL_DIR" || fail "Could not grant $SERVICE_USER read/traverse access to Laravel source files."
mkdir -p "$INSTALL_DIR/storage" "$INSTALL_DIR/bootstrap/cache"
chown -R "$SERVICE_USER:$SERVICE_GROUP" "$INSTALL_DIR/storage" "$INSTALL_DIR/bootstrap/cache"
chmod -R u+rwX,g+rwX,o-rwx "$INSTALL_DIR/storage" "$INSTALL_DIR/bootstrap/cache"
if [[ "$DB_CHOICE" == '1' ]]; then
  chown "$SERVICE_USER:$SERVICE_GROUP" "$INSTALL_DIR/database/database.sqlite"
  chmod 660 "$INSTALL_DIR/database/database.sqlite"
fi
chown "root:$SERVICE_GROUP" "$INSTALL_DIR/.env"
chmod 640 "$INSTALL_DIR/.env"

if ! runuser -u "$SERVICE_USER" -- test -x "$INSTALL_DIR"; then
  namei -l "$INSTALL_DIR" >&2 2>/dev/null || true
  fail "Service user $SERVICE_USER still cannot enter $INSTALL_DIR. Check the path permissions above."
fi
if ! runuser -u "$SERVICE_USER" -- test -r "$INSTALL_DIR/artisan" || ! runuser -u "$SERVICE_USER" -- test -r "$INSTALL_DIR/.env"; then
  fail "Service user $SERVICE_USER cannot read artisan or .env in $INSTALL_DIR."
fi
if ! runuser -u "$SERVICE_USER" -- test -w "$INSTALL_DIR/storage" || ! runuser -u "$SERVICE_USER" -- test -w "$INSTALL_DIR/bootstrap/cache"; then
  fail "Service user $SERVICE_USER cannot write Laravel storage/cache directories in $INSTALL_DIR."
fi
ok "Verified service-user access to $INSTALL_DIR."

PHP_BIN="$(command -v php)"
mkdir -p /etc/cron.d
cat > /etc/cron.d/tr-dashboard <<CRON
* * * * * $SERVICE_USER cd $INSTALL_DIR && $PHP_BIN artisan schedule:run >> /dev/null 2>&1
CRON
chmod 644 /etc/cron.d/tr-dashboard
systemctl enable --now cron 2>/dev/null || systemctl enable --now crond 2>/dev/null || true

if [[ -d /run/systemd/system ]] && command -v systemctl >/dev/null 2>&1; then
  cat > /etc/systemd/system/tr-dashboard.service <<SERVICE
[Unit]
Description=TR Dashboard PHP application server
After=network.target

[Service]
Type=simple
User=$SERVICE_USER
Group=$SERVICE_GROUP
WorkingDirectory=$INSTALL_DIR
ExecStart=$PHP_BIN artisan serve --host=0.0.0.0 --port=1000
Restart=always
RestartSec=3
Environment=APP_ENV=production
UMask=0027
CapabilityBoundingSet=CAP_NET_BIND_SERVICE
AmbientCapabilities=CAP_NET_BIND_SERVICE
NoNewPrivileges=true

[Install]
WantedBy=multi-user.target
SERVICE
  chmod 644 /etc/systemd/system/tr-dashboard.service
  systemctl daemon-reload
  systemctl enable --now tr-dashboard.service
  ok 'Created the systemd service and scheduled lifecycle checks.'
else
  warn 'systemd is not available; starting the PHP server in the background instead.'
  runuser -u "$SERVICE_USER" -- sh -c 'cd "$1" && exec "$2" artisan serve --host=0.0.0.0 --port=1000' _ "$INSTALL_DIR" "$PHP_BIN" \
    >> "$INSTALL_DIR/storage/logs/tr-dashboard-serve.log" 2>&1 &
  ok 'Started the PHP server in the background.'
fi

info 'Checking that the local dashboard responds...'
HTTP_READY=0
for attempt in {1..15}; do
  if curl --silent --show-error --max-time 2 --output /dev/null --write-out '%{http_code}' http://127.0.0.1:1000/ 2>/dev/null | grep -Eq '^[1-5][0-9][0-9]$'; then
    # Any HTTP status proves the PHP server accepted the request; 5xx still means app-level failure.
    HTTP_STATUS="$(curl --silent --show-error --max-time 2 --output /dev/null --write-out '%{http_code}' http://127.0.0.1:1000/ 2>/dev/null || true)"
    if [[ "$HTTP_STATUS" =~ ^[23][0-9][0-9]$ ]]; then HTTP_READY=1; break; fi
  fi
  sleep 1
done
if [[ "$HTTP_READY" -ne 1 ]]; then
  warn 'The service was started, but the HTTP health check did not return 2xx/3xx. Recent service logs follow:'
  if command -v journalctl >/dev/null 2>&1; then journalctl -u tr-dashboard.service -n 80 --no-pager >&2 || true; fi
  if command -v systemctl >/dev/null 2>&1; then systemctl --no-pager --full status tr-dashboard.service >&2 || true; fi
  namei -l "$INSTALL_DIR" >&2 2>/dev/null || true
  fail 'Installation is not confirmed healthy. The application files and database remain in place; review the logs above instead of reinstalling.'
fi

printf '\n%s============================================================%s\n' "$GREEN" "$RESET"
printf '%s TR DASHBOARD INSTALLED SUCCESSFULLY%s\n' "$GREEN" "$RESET"
printf '%s Dashboard : %s%s\n' "$WHITE" "$DASH_URL" "$RESET"
printf '%s Local URL : %shttp://localhost:1000%s\n' "$WHITE" "$CYAN" "$RESET"
printf '%s Admin     : %s%s\n' "$WHITE" "$ADMIN_EMAIL" "$RESET"
printf '%s Files     : %s%s\n' "$WHITE" "$INSTALL_DIR" "$RESET"
printf '%s Settings  : %s/admin/settings%s\n' "$WHITE" "$DASH_URL" "$RESET"
printf '\n%sNext step:%s Sign in as admin, open Admin → Settings, enter the Pterodactyl panel URL and Application API key, set Nest/Egg/Allocation IDs, save, then test the API connection.\n' "$PURPLE" "$RESET"
printf 'For public hosting, point Nginx/Apache to %s/public and enable HTTPS.\n' "$INSTALL_DIR"
printf '%s============================================================%s\n' "$GREEN" "$RESET"
