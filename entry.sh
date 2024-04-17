#!/bin/sh

# Header
echo "Starting up logpager container..."

# List all SQL environment variables
echo "SQL_HOST: $SQL_HOST"
echo "SQL_USER: $SQL_USER"
echo "SQL_PASS: $SQL_PASS"
echo "SQL_DB: $SQL_DB"

# Start MariaDB iff SQL_HOST is localhost
if [ "$SQL_HOST" = "localhost" ]; then
  echo "No remote SQL host. Installing MariaDB..."
  apk add mysql
  mysql_install_db --user=mysql --basedir=/usr --datadir=/var/lib/mysql
  mysqld_safe &
  until mysql -u $SQL_USER -e 'SELECT 1'; do
    echo "Waiting for MySQL container..."
    sleep 3
  done
  sleep 1
  echo "Creating local SQL database and tables..."
  mysql -u $SQL_USER < /db.sql
else
  echo "Using remote host for SQL."
  until mysql -h $SQL_HOST -u $SQL_USER -p$SQL_PASS -e 'SELECT 1' > /dev/null; do
    echo "Waiting for MySQL container..."
    sleep 3
  done
  sleep 1
  echo "Creating SQL database and tables, if needed..."
  mysql -h $SQL_HOST -u $SQL_USER -p$SQL_PASS < /db.sql   
fi
echo "SQL is ready. Continuing..."

# Start PHP-FPM
echo "Starting php-fpm..."
php-fpm83 -R

# Start nginx in the foreground
echo "Starting nginx..."
nginx -g 'daemon off;'

