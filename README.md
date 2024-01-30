# logpager

## About
Lightweight security log file interface written using PHP and JS, intended to provide a dashboard for understanding
security threats. Displays log events as heatmap using tile plot generated with D3, allowing
user to click on a given period to drill down into the log. Performs asynchronous
geolocation and reverse DNS resolution. Clicking on IP address in log shows all events
from that IP, with a heatmap showing history of that IP.

Currently under development, the long-term goal is to provide two things:

- An intuitive high-level view of attacks over time and where they are coming from.
- An efficient interface to proactively identify entire IPs and IP ranges that should be permanently banned, to
supplement existing automated tools like fail2ban.
- A modicum of revenge

## Approach
The approach is to treat the log file itself as truth (rather than have a separate process and database)
and run UNIX tool commands on the host (within the container) to rapidly extract data from the log file
directly, essentially running the kinds of local unix forensic commands a sysadmin would, formatting the results
in a nice user interface and graphical form on the local browser.

## Demo
A public demo of the current development branch may (or may not) be running at <https://nyc.birgefuller.com/logs/>

## Screenshots
![Screenshot 2024-01-21 122840](https://github.com/jonbirge/logpager/assets/660566/d2e5adb1-2308-476d-9c62-3888ceff5bc9)
![Screenshot 2024-01-21 122802](https://github.com/jonbirge/logpager/assets/660566/b2f53624-5f2c-46fc-b75b-58e2eb4c9333)

## Usage
Mount the log file of interest as `/access.log` in the Docker container. Connect
to the container on HTTP port 80 and the default interface will be served. There
is no security or SSL provided as this is primarily intended as an auxilary
container to be integrated with other containers and hosted behind a reverse
proxy, such as Traefik. Right now this only work with CLF log files, but will
eventually be made to work with at least standard auth logs, as well.

## With docker-compose
Here is an example docker-compose.yml file showing how to integrate with a
reverse proxy (Traefik) to access logs for all proxy traffic.
```
version: "3.7"

services:
  traefik:
    image: traefik
    restart: always
    command:
      - "--certificatesresolvers.stackresolver.acme.email=$EMAIL"
      - "--configFile=/etc/traefik.yml"
    ports:
      - "80:80"
      - "443:443"
      - "28080:8080"  # traefik admin
    volumes:
      - ./logs/:/logs/:rw
      - /var/run/docker.sock:/var/run/docker.sock
      - ./certs:/letsencrypt
      - ./traefik.yml:/etc/traefik.yml:ro
      - ./traefik:/etc/traefik
    depends_on:
      - logpager
      - livetrace
      - guac
      - www
      - adminer

  logpager:
    image: ghcr.io/jonbirge/logpager:dev
    labels:
      - "traefik.http.routers.logpagerdev.rule=Host(`$HOSTNAME`) && PathPrefix(`/logs`)"
      - "traefik.http.routers.logpagerdev.tls.certresolver=stackresolver"
      - "traefik.http.middlewares.striplogdev.stripprefix.prefixes=/logs/"
      - "traefik.http.routers.logpagerdev.middlewares=topten@file,striplogdev"
    volumes:
      - /var/log/auth.log:/auth.log:ro
      - ./logs/access.log:/access.log:ro
      - ./logs/excludes.json:/excludes.json:ro
      - ./logs/blacklist:/blacklist:rw
```
