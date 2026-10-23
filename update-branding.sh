#!/usr/bin/env bash
set -Eeuo pipefail

PACKAGE_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="${1:-/var/www/tr-dashboard}"

if [[ ! -f "$APP_DIR/artisan" || ! -d "$APP_DIR/app" ]]; then
  printf 'ERROR: %s does not look like a Laravel application (artisan not found).\n' "$APP_DIR" >&2
  printf 'Usage: sudo ./update-branding.sh /path/to/tr-dashboard\n' >&2
  exit 1
fi

STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP_ROOT="${TR_BACKUP_ROOT:-${HOME:-/root}/tr-dashboard-branding-backups}"
BACKUP_DIR="$BACKUP_ROOT/$STAMP"
mkdir -p "$BACKUP_DIR"

FILES=(
  "resources/views/layouts/app.blade.php"
  "resources/views/layouts/auth.blade.php"
  "resources/views/admin/settings/branding.blade.php"
  "app/Http/Controllers/AdminSettingsController.php"
  "app/Support/Settings.php"
  "public/tr-dashboard.css"
  "public/tr-logo.png"
)

for rel in "${FILES[@]}"; do
  src="$PACKAGE_DIR/payload/$rel"
  dest="$APP_DIR/$rel"
  [[ -f "$src" ]] || { printf 'ERROR: package file missing: %s\n' "$src" >&2; exit 1; }
  mkdir -p "$(dirname "$dest")" "$BACKUP_DIR/$(dirname "$rel")"
  if [[ -f "$dest" ]]; then
    cp -a "$dest" "$BACKUP_DIR/$rel"
    install -m 0644 "$src" "$dest"
    if [[ "$EUID" -eq 0 ]]; then chown --reference="$BACKUP_DIR/$rel" "$dest" 2>/dev/null || true; fi
  else
    install -m 0644 "$src" "$dest"
    if [[ "$EUID" -eq 0 && -f "$APP_DIR/public/index.php" ]]; then chown --reference="$APP_DIR/public/index.php" "$dest" 2>/dev/null || true; fi
  fi
  printf 'Updated %s\n' "$rel"
done

APP_OWNER="$(stat -c '%U' "$APP_DIR/storage" 2>/dev/null || printf '')"
if [[ "$EUID" -eq 0 && -n "$APP_OWNER" && "$APP_OWNER" != root ]] && command -v runuser >/dev/null 2>&1; then
  runuser -u "$APP_OWNER" -- bash -lc "cd '$APP_DIR' && php artisan optimize:clear"
else
  (cd "$APP_DIR" && php artisan optimize:clear)
fi

printf '\nBranding update complete.\nBackup: %s\n' "$BACKUP_DIR"
printf 'Creator credit: Officially made by TheRynzo\n'
