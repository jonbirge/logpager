# logpager

## About
Lightweight log file web interface written using PHP and JS, intended to provide a dashboard for security
threats. Displays log events as heatmap using tile plot generated with D3, allowing
user to click on a given period to drill down into the log. Performs asynchronous
reverse-DNS hostname resolution. User can click on IP address in log and see all events
from that IP, with a new heatmap showing counts over time just for that IP.

Currently under development, the long-term goal is to provide two things:

- An intuitive highlevel understanding of attacks over time and where they are coming from.
- An efficient interface to proactively identify entire IP ranges that should be black-listed and do so,
supplementing simple "fail to ban" automation that only works with single IP addresses and only
temporarily. While much more effective than automated banning, a human should probably be involved.

## Approach
The approach is to treat the log file itself as truth (rather than have a separate process and database)
and run UNIX tool commands on the host (within the container) to rapidly extract data from the log file
directly, essentially running the kinds of forensic commands a sysadmin would, formatting the results
in a nice user interface and graphical form on the local browser.

## Screenshot
![Screenshot 2024-01-08 195757](https://github.com/jonbirge/logpager/assets/660566/1008eb11-232c-444f-b286-216dd362da30)

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
    volumes:
      - ./logs/:/logs/:rw
      - /var/run/docker.sock:/var/run/docker.sock
      - ./certs:/letsencrypt
      - ./traefik.yml:/etc/traefik.yml:ro
      - ./traefik:/etc/traefik

  logpager:
    image: ghcr.io/jonbirge/logpager:master
    restart: always
    labels:
      - "traefik.http.routers.logpager.rule=Host(`$HOSTNAME`) && PathPrefix(`/logs`)"
      - "traefik.http.routers.logpager.tls.certresolver=stackresolver"
      - "traefik.http.middlewares.strip-log.stripprefix.prefixes=/logs/"
      - "traefik.http.routers.logpager.middlewares=strip-log"
    volumes:
      - ./logs/access.log:/access.log:ro
      - ./logs/excludes.json:/excludes.json:ro
```
