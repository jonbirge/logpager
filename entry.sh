#!/bin/sh

# Start PHP-FPM
echo "Starting PHP-FPM..."
php-fpm82

# Start nginx in the foreground
echo "Starting nginx..."
nginx -g 'daemon off;'
