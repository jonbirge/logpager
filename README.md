# log-pager

## Overview

Lightweight security log inspection and blacklisting web interface, intended to provide a dashboard for threats. Displays log events as heatmap using tile plot, allowing user to click on a given period to drill down into the log. Performs asynchronous geolocation and reverse DNS resolution.

Right now this will handle web logs, Traefik logs, and auth logs (e.g. sshd), but is designed to allow other log types to be added via a quasi-object-oriented programming interface.

### Threat intel and blocking

For each IP in the log, there is a button to pull intel about the IP, including port scans, whois, traceroute and ping graphs. There is also a button to add the IP to a local blocklist. The intent here is to supplement automated "fail to ban" approaches with the potential for manual permanent blocking by a human administrator.

Long term, the goal is to have a central service that will take allow everybody using this software to contribute and subscribe to a common blocklist. If you're interested in this, please reach out to admin@birgefuller.com.

### Technical approach

The approach is to treat the log file itself as truth and run UNIX tool commands on the host (within a Docker container) to extract data from the log file directly, essentially running the kinds of local unix forensic commands a sysadmin would. This approach is intended to minimize the impact on the server, with no resources being used except when the web interface is actively being used.

## Demo

You can run a demo container stack locally by running `make up` at the top level of the distribution and connecting a browser to <http://localhost/logs/>. In addition, a public demo of the current development branch may (or may not) be running at <https://nyc.birgefuller.com/logs/>.

## Screenshots

### Default log display

![Screenshot 2024-05-19 165426](https://github.com/jonbirge/logpager/assets/660566/52c76b9b-dc43-480f-a568-02d4b393b41c)

### Search display

![Screenshot 2024-05-19 165513](https://github.com/jonbirge/logpager/assets/660566/4fb13ee7-2e25-4ef3-816d-0cb2d2919363)

### Intel page

![Screenshot 2024-05-19 170507](https://github.com/jonbirge/logpager/assets/660566/ce08c7b3-111e-489b-815d-52241d9d7087)

## Docker image

You can get a pre-built image from the Packages section here, or from Docker Hub at <https://hub.docker.com/r/jonbirge/logpager>.

### Usage

Mount your server's log files into the logpager container as shown below. Connect to the container on HTTP port 80 and the default interface will be served. There is no security or SSL provided as this is primarily intended as an auxilary container to be integrated with other containers and hosted behind a reverse proxy, such as Traefik.

Export `/blacklist.csv` to provide a live list of blacklisted IP addresses and CIDRs. There are scripts in `/utils` showing how to use this file to update iptable-based firewalls in Linux.

### Docker Compose

The easiest way to use this is within an orchestrated set of containers that includes a reverse proxy and an SQL database (to handle the backlisting functionality). You can quickly stand up a fully functional instance of logpager using the `docker-compose.yml` file found in `/test/stack`. Below is an example `docker-compose.yml` file showing how to integrate with a reverse proxy (Traefik) to access logs for all proxy traffic.

```

services:
  traefik:
    image: traefik
    restart: always
    command:
      - "--accesslog.filepath=/logs/access.log"
      - "--providers.file.directory=/etc/traefik"
      - "--providers.file.watch=true"
      - "--providers.docker.exposedbydefault=true"
      - "--api.insecure=true"
      - "--entrypoints.web.address=:80"
      - "--experimental.plugins.denyip.modulename=github.com/kevtainer/denyip"
      - "--experimental.plugins.denyip.version=v1.0.0"
    ports:
      - "80:80"
      - "8080:8080" # Traefik admin
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock
      - ./traefik:/etc/traefik
      - ./logs/:/logs/:rw
    depends_on:
      - logpager

  logpager:
    image: ghcr.io/jonbirge/logpager:dev
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
      - ../../src:/var/www:ro  # live development
      - ../logs/auth.log:/auth.log:ro  # test auth logs
      - ../logs/clf.log:/clf.log:ro  # test clf logs
      - ./logs/access.log:/access.log:ro  # actual logs from this stack
    depends_on:
      - db

  db:
    image: mysql
    restart: always
    volumes:
      - dbdata:/var/lib/mysql
    environment:
      MYSQL_ROOT_PASSWORD: testpass

  adminer:
    image: adminer
    restart: always
    environment:
      ADMINER_DESIGN: nette
    labels:
      - "traefik.http.routers.adminer.rule=PathPrefix(`/adminer`)"
      - "traefik.port=8080"
    depends_on:
      - db

volumes:
  dbdata:

```
