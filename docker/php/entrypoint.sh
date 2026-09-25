#!/bin/bash
set -e

# Ensure storage and bootstrap/cache are writable
# We check if we have enough permissions to do chown, otherwise we just try chmod
echo "Setting permissions for storage and bootstrap/cache..."
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true

# Execute the main command
exec "$@"
