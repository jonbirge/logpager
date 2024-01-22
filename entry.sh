#!/bin/sh

# Start PHP-FPM
echo "Starting php-fpm83..."
php-fpm83

# Start nginx in the foreground
echo "Starting nginx..."
nginx -g 'daemon off;'

