# Alpine Linux as the base image
FROM alpine:latest

# Install nginx and PHP
# RUN apk update && apk upgrade
RUN apk add --no-cache nginx php83-fpm whois

# Setup Nginx web root
RUN rm -rf /var/www
RUN mkdir -p /var/www
RUN chown -R nginx:nginx /var/www

# Copy Nginx configuration file
COPY default.conf /etc/nginx/http.d/default.conf

# Default /blacklist file and make writable by php-fpm
RUN touch /blacklist
RUN chmod a+w /blacklist

# Copy exclusion list
COPY excludes.json /

# Startup script
COPY entry.sh /entry.sh

# Copy the source files to the Nginx web root
COPY src/* /var/www/

# Copy test log files during testing
ARG TESTLOGS=false
RUN mkdir -p /var/testlogs
COPY testlogs/* /var/testlogs/
RUN chown -R nginx:nginx /var/testlogs
RUN if [ "$TESTLOGS" = "true" ] ; then \
    cp /var/testlogs/* / ; \
    fi

# Expose standard HTTP port 
EXPOSE 80

# Start nginx and PHP-FPM
CMD ["/entry.sh"]

