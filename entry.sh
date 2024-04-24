#!/bin/sh

# Header
echo "Starting up logpager container..."

# List all SQL environment variables
echo "SQL_HOST: $SQL_HOST"
echo "SQL_USER: $SQL_USER"
echo "SQL_PASS: $SQL_PASS"
echo "SQL_DB: $SQL_DB"

# Wait for SQL...
# until mysql -h $SQL_HOST -u $SQL_USER -p$SQL_PASS -e 'SELECT 1' > /dev/null; do
  echo "Waiting for MySQL..."
  sleep 5
# done
# echo "Creating SQL database and tables, if needed..."
# mysql -h $SQL_HOST -u $SQL_USER -p$SQL_PASS < /db.sql   
echo "SQL is ready. Continuing..."

# Start PHP-FPM
echo "Starting php-fpm..."
php-fpm83 -R

# Start nginx in the foreground
echo "Starting nginx..."
nginx -g 'daemon off;'

