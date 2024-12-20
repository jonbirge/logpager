# Alpine Linux as the base image
# NOTE: alpine:3.21 break the MariaDB client
FROM alpine:3.20

# Labels
LABEL org.opencontainers.image.source=https://github.com/jonbirge/logpager
LABEL org.opencontainers.image.description="Web-based log file forensics tool container"
LABEL org.opencontainers.image.licenses=MIT

# Install & configure nginx/PHP-FPM/SQL stack
RUN apk --no-cache update && apk --no-cache upgrade
RUN apk add --no-cache nginx php83-fpm php83-mysqli
RUN apk add --no-cache whois tcptraceroute nmap nmap-scripts
RUN echo "variables_order = 'EGPCS'" > /etc/php83/conf.d/00_variables.ini
RUN rm -rf /var/www && mkdir -p /var/www && chown -R nginx:nginx /var/www

# Install SQL client
RUN apk add --no-cache mariadb-client mariadb-connector-c-dev

# Setup default environment variables for local SQL
ENV SQL_HOST=localhost
ENV SQL_USER=root
ENV SQL_PASS=""
ENV SQL_DB=logpager

# setuid root
RUN chmod u+s /usr/bin/tcptraceroute /usr/bin/nmap

# Copy the configuration files
COPY conf/www.conf /etc/php83/php-fpm.d/www.conf
COPY conf/default.conf /etc/nginx/http.d/default.conf
COPY conf/db.sql /db.sql

# Copy the source files to the web root
COPY src/ /var/www/

# Startup script
COPY docker/entry.sh /entry.sh

# Expose HTTP port 
EXPOSE 80

# Startup script
CMD ["/entry.sh"]
