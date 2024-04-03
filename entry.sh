#!/bin/sh

# List all SQL environment variables
echo "SQL_HOST: $SQL_HOST"
echo "SQL_USER: $SQL_USER"
echo "SQL_PASS: $SQL_PASS"
echo "SQL_DB: $SQL_DB"

# Start MariaDB
echo "Starting SQL..."
mysqld_safe &

# Start PHP-FPM
echo "Starting php-fpm..."
php-fpm82 -R

# Create SQL database and table if they don't exist
sleep 5
echo "Creating SQL database and tables as needed..."
mysql < /db.sql

# Start nginx in the foreground
echo "Starting nginx..."
nginx -g 'daemon off;'
