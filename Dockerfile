# Alpine Linux as the base image
FROM alpine:latest

# Install Nginx and PHP
RUN apk update && apk upgrade
RUN apk add --no-cache nginx php-fpm

# Setup Nginx web root
RUN rm -rf /var/www
RUN mkdir -p /var/www
RUN chown -R nginx:nginx /var/www

# Copy the Nginx configuration file
COPY default.conf /etc/nginx/http.d/default.conf

# Copy test log file
COPY test.log /access.log

# Startup script
COPY entry.sh /entry.sh

# Copy the files to the Nginx web root
COPY *.html *.php *.js *.css /var/www/

# Expose port 
EXPOSE 80

# Start Nginx and PHP-FPM
CMD ["/entry.sh"]
