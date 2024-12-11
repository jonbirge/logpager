#!/bin/sh

# Header
echo "Starting up logpager container..."

# List all SQL environment variables
echo "SQL_HOST: $SQL_HOST"
echo "SQL_USER: $SQL_USER"
echo "SQL_PASS: $SQL_PASS"
echo "SQL_DB: $SQL_DB"

# Wait for SQL...
echo "Waiting for MariaDB..."
sleep 5

# Start PHP-FPM
echo "Starting php-fpm..."
php-fpm83 -R

# Start nginx in the foreground
echo "Starting nginx..."
nginx -g 'daemon off;'

