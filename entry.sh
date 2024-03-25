#!/bin/sh

# Create SQL database and table if they don't exist
echo "Creating database and table..."
mysql -h $SQL_HOST -u $SQL_USER -p $SQL_PASS $SQL_DB < /db.sql

# Start PHP-FPM
echo "Starting php-fpm..."
php-fpm82

# Start nginx in the foreground
echo "Starting nginx..."
nginx -g 'daemon off;'
