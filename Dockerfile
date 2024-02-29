# Alpine Linux as the base image
FROM alpine:3.18

# Labels
LABEL org.opencontainers.image.source=https://github.com/jonbirge/logpager
LABEL org.opencontainers.image.description="Web-based log file forensics tool"
LABEL org.opencontainers.image.licenses=MIT

# Install nginx, PHP and others
RUN apk update && apk upgrade
RUN apk add --no-cache nginx php82-fpm
RUN apk add --no-cache whois tcptraceroute nmap nmap-scripts

# setuid root
RUN chmod u+s /usr/bin/tcptraceroute
RUN chmod u+s /usr/bin/nmap

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
COPY src/ /var/www/

# Expose standard HTTP port 
EXPOSE 80

# Start nginx and PHP-FPM
CMD ["/entry.sh"]
