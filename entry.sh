#!/bin/sh

# Start PHP-FPM
echo "Starting php-fpm..."
php-fpm82

# Start nginx in the foreground
echo "Starting nginx..."
nginx -g 'daemon off;'
