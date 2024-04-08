# Alpine Linux as the base image
FROM alpine:3.18

# Labels
LABEL org.opencontainers.image.source=https://github.com/jonbirge/logpager
LABEL org.opencontainers.image.description="Web-based log file forensics tool container"
LABEL org.opencontainers.image.licenses=MIT

# Install & configure nginx/PHP-FPM/MySQL stack
RUN apk update && apk upgrade
RUN apk add --no-cache mariadb-client mariadb-connector-c-dev
RUN apk add --no-cache nginx php82-fpm php82-mysqli
RUN apk add --no-cache whois tcptraceroute nmap nmap-scripts
COPY www.conf /etc/php82/php-fpm.d/www.conf
COPY default.conf /etc/nginx/http.d/default.conf
RUN echo "variables_order = 'EGPCS'" > /etc/php82/conf.d/00_variables.ini
RUN rm -rf /var/www && mkdir -p /var/www && chown -R nginx:nginx /var/www

# Setup default environment variables for local SQL
ENV SQL_HOST=localhost
ENV SQL_USER=root
ENV SQL_PASS=""
ENV SQL_DB=logpager

# setuid root
RUN chmod u+s /usr/bin/tcptraceroute /usr/bin/nmap

# Copy test log files
RUN mkdir -p /var/testlogs
COPY test/*.log /var/testlogs/
RUN chown -R nginx:nginx /var/testlogs && cp /var/testlogs/* /

# Default /blacklist file and make writable by php-fpm
RUN touch /blacklist && chmod a+w /blacklist

# Startup scripts
COPY db.sql /db.sql
COPY entry.sh /entry.sh

# Copy the source files to the Nginx web root
COPY src/ /var/www/

# Expose HTTP port 
EXPOSE 80

# Start
CMD ["/entry.sh"]
