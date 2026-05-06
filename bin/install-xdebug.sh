#!/bin/sh
# Install Xdebug into the wp-env tests-cli container for code coverage.
# Called automatically via the afterStart lifecycle script in .wp-env.json.
# Safe to re-run: skips installation if xdebug.so already exists.

set -e

XDEBUG_INI=/usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
XDEBUG_SO=$(php -r 'echo ini_get("extension_dir");')/xdebug.so

if [ -f "$XDEBUG_SO" ] && grep -q "zend_extension" "$XDEBUG_INI" 2>/dev/null; then
	echo "Xdebug already installed — skipping."
	exit 0
fi

echo "Installing Xdebug..."
apk add --no-cache $PHPIZE_DEPS linux-headers >/dev/null 2>&1
pecl install xdebug >/dev/null 2>&1
printf 'zend_extension=xdebug.so\n[xdebug]\nxdebug.mode=coverage\n' > "$XDEBUG_INI"
echo "Xdebug installed: $(php -r 'echo phpversion(\"xdebug\");')"
