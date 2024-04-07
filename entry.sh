#!/bin/sh

# List all SQL environment variables
echo "SQL_HOST: $SQL_HOST"
echo "SQL_USER: $SQL_USER"
echo "SQL_PASS: $SQL_PASS"
echo "SQL_DB: $SQL_DB"

# Start MariaDB iff SQL_HOST is localhost
if [ "$SQL_HOST" = "localhost" ]; then
    echo "Starting SQL..."
    mysqld_safe &
else
    echo "Using remote host for SQL. Skipping SQL startup."
fi

# Start PHP-FPM
echo "Starting php-fpm..."
php-fpm82 -R

# Wait for MySQL
until mysql -h $SQL_HOST -u $SQL_USER -p$SQL_PASS -e 'SELECT 1'; do
  echo "Waiting for MySQL container..."
  sleep 3
done

# Continue with your operations
echo "MySQL is ready. Continuing..."

# Create SQL database and table if they don't exist
sleep 1
echo "Creating SQL database and tables as needed..."
mysql -h $SQL_HOST -u $SQL_USER -p$SQL_PASS < /db.sql

# Start nginx in the foreground
echo "Starting nginx..."
nginx -g 'daemon off;'
