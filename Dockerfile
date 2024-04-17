# Alpine Linux as the base image
FROM alpine:3.19

# Labels
LABEL org.opencontainers.image.source=https://github.com/jonbirge/logpager
LABEL org.opencontainers.image.description="Web-based log file forensics tool container"
LABEL org.opencontainers.image.licenses=MIT

# Install & configure nginx/PHP-FPM/SQL stack
RUN apk --no-cache update && apk --no-cache upgrade
RUN apk add --no-cache mariadb-client mariadb-connector-c-dev
RUN apk add --no-cache nginx php83-fpm php83-mysqli
RUN apk add --no-cache whois tcptraceroute nmap nmap-scripts
RUN echo "variables_order = 'EGPCS'" > /etc/php83/conf.d/00_variables.ini
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
COPY test/logs/*.log /var/testlogs/
RUN chown -R nginx:nginx /var/testlogs && cp /var/testlogs/* /

# Server configuration
COPY conf/www.conf /etc/php83/php-fpm.d/www.conf
COPY conf/default.conf /etc/nginx/http.d/default.conf
COPY conf/db.sql /db.sql

# Startup scripts
COPY entry.sh /entry.sh

# Copy the source files to the nginx web root
RUN mkdir -p /var/www
COPY backend/ /var/www/
COPY frontend/ /var/www/

# Expose HTTP port 
EXPOSE 80

# Start
CMD ["/entry.sh"]
