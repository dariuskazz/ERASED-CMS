#!/bin/sh
set -e
mkdir -p /var/www/html/storage/uploads
chown -R www-data:www-data /var/www/html/storage
chmod -R 775 /var/www/html/storage
exec "$@"
