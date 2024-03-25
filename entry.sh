#!/bin/sh

# Create SQL database and table if they don't exist
echo "Creating database and table..."
mysql -h $DB_HOST -u $DB_USER -p$DB_PASSWORD $DB_NAME < /db.sql

# Start PHP-FPM
echo "Starting php-fpm..."
php-fpm82

# Start nginx in the foreground
echo "Starting nginx..."
nginx -g 'daemon off;'
