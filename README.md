# logpager

## About
Common Log Format web interface written using PHP and JS. Displays log events
as heatmap using tile plot generated with D3, allowing user to click on a given
period to drill down into the log. Performs asynchronous reverse-DNS
hostname resolution. User can click on IP address in log and see all events
from that IP, with a new heatmap showing counts over time just for that IP.

## Screenshot
![Screenshot 2024-01-08 195757](https://github.com/jonbirge/logpager/assets/660566/1008eb11-232c-444f-b286-216dd362da30)

## Usage
Mount the log file of interest as `/access.log` in the Docker container. Connect
to the container on HTTP port 80 and the default interface will be served. There
is no security or SSL provided as this is primarily intended as an auxilary
container to be integrated with other containers and hosted behind a reverse
proxy, such as Traefik. Right now this only work with CLF log files, but will
eventually be made to work with at least standard auth logs, as well.

## Using with docker-compose
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
