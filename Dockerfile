# Alpine Linux as the base image
FROM alpine:latest

# Install nginx and PHP
RUN apk update && apk upgrade
RUN apk add --no-cache nginx php-fpm

# Setup Nginx web root
RUN rm -rf /var/www
RUN mkdir -p /var/www
RUN chown -R nginx:nginx /var/www

# Copy the Nginx configuration file
COPY default.conf /etc/nginx/http.d/default.conf

# Startup script
COPY entry.sh /entry.sh

# Copy test log file so this runs even if there is no volume mounted
COPY test.log /access.log

# Copy the files to the Nginx web root
COPY *.html *.php *.js *.css /var/www/

# Expose standard HTTP port 
EXPOSE 80

# Start nginx and PHP-FPM
CMD ["/entry.sh"]
