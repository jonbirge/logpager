#!/bin/sh

# List all SQL environment variables
echo "SQL_HOST: $SQL_HOST"
echo "SQL_PORT: $SQL_PORT"
echo "SQL_USER: $SQL_USER"
echo "SQL_PASS: $SQL_PASS"
echo "SQL_DB: $SQL_DB"

# Start MariaDB
echo "Starting SQL..."
mysqld_safe &

# Start PHP-FPM
echo "Starting php-fpm..."
php-fpm82

# Create SQL database and table if they don't exist
sleep 5
echo "Creating database and table if needed..."
mysql < /db.sql

# Start nginx in the foreground
echo "Starting nginx..."
nginx -g 'daemon off;'
