#!/bin/sh

# Header
echo "Starting up logpager container..."

# List all SQL environment variables
echo "SQL_HOST: $SQL_HOST"
echo "SQL_USER: $SQL_USER"
echo "SQL_PASS: $SQL_PASS"
echo "SQL_DB: $SQL_DB"

echo "Initializing SQL schema..."
if ! php84 /db-init.php; then
  echo "Failed to initialize SQL. Exiting."
  exit 1
fi

# Start PHP-FPM
echo "Starting php-fpm..."
php-fpm84 -R

# Start nginx in the foreground
echo "Starting nginx..."
nginx -g 'daemon off;'

