# Alpine Linux as the base image
FROM alpine:3.18

# Labels
LABEL org.opencontainers.image.source=https://github.com/jonbirge/logpager-test
LABEL org.opencontainers.image.description="Web-based log file forensics tool test container"
LABEL org.opencontainers.image.licenses=MIT

# Install & configure nginx/PHP-FPM/MySQL stack
RUN apk update && apk upgrade
RUN apk add --no-cache mariadb mariadb-client
RUN mysql_install_db --user=mysql --basedir=/usr --datadir=/var/lib/mysql
RUN apk add --no-cache nginx php82-fpm
RUN apk add --no-cache php82-mysqli
RUN apk add --no-cache whois tcptraceroute nmap nmap-scripts
COPY www.conf /etc/php82/php-fpm.d/www.conf
RUN echo "variables_order = 'EGPCS'" > /etc/php82/conf.d/00_variables.ini
COPY default.conf /etc/nginx/http.d/default.conf
RUN rm -rf /var/www && mkdir -p /var/www && chown -R nginx:nginx /var/www

# Use anonymous volume for MySQL server
VOLUME ["/var/lib/mysql"]

# Setup default environment variables for SQL
ENV SQL_HOST=localhost
ENV SQL_USER=root
ENV SQL_PASS=
ENV SQL_DB="logpager"

# setuid root
RUN chmod u+s /usr/bin/tcptraceroute
RUN chmod u+s /usr/bin/nmap

# Copy test log files during testing
RUN mkdir -p /var/testlogs
COPY testlogs/* /var/testlogs/
RUN chown -R nginx:nginx /var/testlogs
RUN cp /var/testlogs/* /

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
