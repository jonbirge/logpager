# Alpine Linux as the base image
FROM alpine:latest

# Install nginx and PHP
# RUN apk update && apk upgrade
RUN apk add --no-cache nginx php83-fpm whois tcptraceroute

# Set tcptraceroute as setuid root
RUN chmod u+s /usr/bin/tcptraceroute

# Setup Nginx web root
RUN rm -rf /var/www && mkdir -p /var/www && chown -R nginx:nginx /var/www

# Copy Nginx configuration file
COPY default.conf /etc/nginx/http.d/default.conf

# Copy test log files during testing
ARG TESTLOGS=false
RUN mkdir -p /var/testlogs
COPY testlogs/* /var/testlogs/
RUN chown -R nginx:nginx /var/testlogs
RUN if [ "$TESTLOGS" = "true" ] ; then \
    cp /var/testlogs/* / ; \
    fi

# Default /blacklist file and make writable by php-fpm
RUN touch /blacklist && chmod a+w /blacklist

# Startup script
COPY entry.sh /entry.sh

# Copy the source files to the Nginx web root
COPY src/* /var/www/

# Expose standard HTTP port 
EXPOSE 80

# Start nginx and PHP-FPM
CMD ["/entry.sh"]
