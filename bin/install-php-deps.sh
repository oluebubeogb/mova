#!/usr/bin/env bash
# Mova — install the PHP extensions Mova needs on a VPS (Debian/Ubuntu, RHEL/Fedora, Alpine).
# Usage: sudo bash bin/install-php-deps.sh
# Shared hosting: ask your host to enable these extensions instead (cPanel → Select PHP Version).
set -euo pipefail

if [ "$(id -u)" -ne 0 ]; then
  echo "Please run as root: sudo bash bin/install-php-deps.sh" >&2
  exit 1
fi

PHPV="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null || true)"
echo "Detected PHP: ${PHPV:-not installed}"

if command -v apt-get >/dev/null 2>&1; then
  apt-get update
  P="php${PHPV}"
  [ -z "$PHPV" ] && P="php"
  apt-get install -y "${P}-sqlite3" "${P}-gd" "${P}-intl" "${P}-mbstring" "${P}-zip" "${P}-curl" "${P}-xml" "${P}-opcache" || true
  apt-get install -y "${P}-imap" 2>/dev/null || echo "Note: ${P}-imap unavailable (Mailbox IMAP is optional)."
elif command -v dnf >/dev/null 2>&1; then
  dnf install -y php-pdo php-gd php-intl php-mbstring php-pecl-zip php-xml php-opcache php-sodium || true
  dnf install -y php-imap 2>/dev/null || echo "Note: php-imap unavailable (Mailbox IMAP is optional)."
elif command -v apk >/dev/null 2>&1; then
  V="$(echo "${PHPV:-83}" | tr -d .)"
  apk add --no-cache "php${V}-pdo_sqlite" "php${V}-gd" "php${V}-intl" "php${V}-mbstring" "php${V}-zip" "php${V}-curl" "php${V}-opcache" "php${V}-sodium" || true
  apk add --no-cache "php${V}-imap" 2>/dev/null || echo "Note: php${V}-imap unavailable (Mailbox IMAP is optional)."
else
  echo "Unsupported package manager. Enable: pdo_sqlite, gd, intl, mbstring, zip, curl, opcache (imap optional)." >&2
  exit 2
fi

echo
echo "Done. Restart PHP-FPM / your web server, e.g.:"
echo "  systemctl restart php${PHPV}-fpm 2>/dev/null || systemctl restart php-fpm || service apache2 restart"
php -m | grep -Ei 'pdo_sqlite|gd|intl|mbstring|zip|curl|imap|sodium' || true
