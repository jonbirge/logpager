# log-pager

## Overview
Lightweight security log forensics and blacklisting web interface, intended to
provide a dashboard for threats. Displays log events as heatmap using tile plot,
allowing user to click on a given period to drill down into the log. Performs
asynchronous geolocation and reverse DNS resolution.

### Threat intel and blocking
For each IP in the log, there is a button to pull intel about an IP, including
port scans, whois, traceroute and ping graphs. There is also a button to add the
IP to a blocklist if desired. The intent here is to supplement automated "fail
to ban" approaches with the potential for manual permanent blocking by a human
administrator.

### Approach
The approach is to treat the log file itself as truth and run UNIX tool commands
on the host (within a Docker container) to extract data from the log file
directly, essentially running the kinds of local unix forensic commands a
sysadmin would. This approach is intended to minimize the impact on the server,
with no resources being used except when the web interface is actively being
used.

### Potential blacklist service
If enough people adopt this, the next step will be to build a service that would
collect blacklisted IPs (if you voluntarily configured your instance of
log-pager to forward blacklist items) and publicly provide provde aggregated
blacklists based on crowdsourced human judgement, rather than algorithms. If you
think this would be interesting, please reach out.

## Demo
A public demo of the current development branch may (or may not) be running at
<https://nyc.birgefuller.com/logs/>

## Screenshots

### Default log display
![Screenshot 2024-05-19
165426](https://github.com/jonbirge/logpager/assets/660566/52c76b9b-dc43-480f-a568-02d4b393b41c)

### Search display
![Screenshot 2024-05-19
165513](https://github.com/jonbirge/logpager/assets/660566/4fb13ee7-2e25-4ef3-816d-0cb2d2919363)

### Intel page
![Screenshot 2024-05-19
170507](https://github.com/jonbirge/logpager/assets/660566/ce08c7b3-111e-489b-815d-52241d9d7087)

## Docker image
This repo automatically builds a Docker image that can be pulled from the GitHub
Container Registry. See the Packages tab in the GitHub repo for the latest
version.

### General usage
Mount the log files of interest as `/access.log` and `/auth.log` in the Docker
container. Connect to the container on HTTP port 80 and the default interface
will be served. There is no security or SSL provided as this is primarily
intended as an auxilary container to be integrated with other containers and
hosted behind a reverse proxy, such as Traefik. Right now this only work with
CLF log files, but will eventually be made to work with at least standard auth
logs, as well.

You can quickly stand up a fully functional demo using the `docker-compose.yml`
file found in `/test/stack`.

### Use with `docker-compose`
Here is an example docker-compose.yml file showing how to integrate with a
reverse proxy (Traefik) to access logs for all proxy traffic. (Obviously, you'll
want to have other services as well which I haven't shown here.)
```
services:

  traefik:
    image: traefik
    restart: always
    command:
      - "--configFile=/etc/traefik.yml"
    ports:
      - "80:80"
      - "8080:8080"  # traefik admin
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock
      - ./traefik.yml:/etc/traefik.yml:ro
      - ./traefik:/etc/traefik
      - ./logs/:/logs/:rw
    depends_on:
      - logpager
      - www

  logpager:
    image: logpager_test
    restart: always
    environment:
      SQL_HOST: db
      SQL_PASS: testpass
      SQL_USER: root
    labels:
      - "traefik.http.routers.logpagerdev.rule=PathPrefix(`/logs`)"
      - "traefik.http.middlewares.striplogdev.stripprefix.prefixes=/logs/"
      - "traefik.http.routers.logpagerdev.middlewares=striplogdev"
    volumes:
      - /var/logs/auth.log:/auth.log:ro
      - ./logs/access.log:/access.log:ro
    depends_on:
      - db

  db:
    image: mysql
    restart: always
    volumes:
      - dbdata:/var/lib/mysql
    environment:
      MYSQL_ROOT_PASSWORD: testpass

  www:
    image: nginx
    restart: always
    labels:
      - "traefik.http.routers.www.rule=PathPrefix(`/`)"
      - "traefik.http.routers.www.middlewares=blacklist@file"
    volumes:
      - ./www:/usr/share/nginx/html:rw
      - ./nginx.conf:/etc/nginx/nginx.conf:ro

  # Other services...
```
