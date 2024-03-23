# log-forensics

## Overview
Lightweight security log auditing and forensics written using PHP and JavaScript,
intended to provide a dashboard for threats and a quick interface to block them.
Displays log events as heatmap using tile plot generated with D3, allowing user
to click on a given period to drill down into the log. Performs asynchronous
geolocation and reverse DNS resolution. Clicking on IP address in log shows
all events from that IP, with a heatmap showing history of that IP.

### Threat intel and blocking
For each IP in the log, there is a button to pull intel about an IP, including port scans, whois,
traceroute and ping graphs. There is also a button to add the IP to a blocklist
if desired. The intent here is to supplement automated "fail to ban" approaches with
the potential for manual permanent blocking by a human administrator.

### Approach
The approach is to treat the log file itself as truth and run UNIX tool commands
on the host (within a Docker container) to extract data from the log file
directly, essentially running the kinds of local unix forensic commands a
sysadmin would. These commands are scripted using PHP, with the data formatting
done locally in the browser using JavaScript. This approach is intended to
minimize the impact on the server.

## Demo
A public demo of the current development branch may (or may not) be running
at <https://nyc.birgefuller.com/logs/>

## Screenshots
![Screenshot 2024-01-21 122840](https://github.com/jonbirge/logpager/assets/660566/d2e5adb1-2308-476d-9c62-3888ceff5bc9)
![Screenshot 2024-01-21 122802](https://github.com/jonbirge/logpager/assets/660566/b2f53624-5f2c-46fc-b75b-58e2eb4c9333)

## Docker image
This repo automatically builds a Docker image that can be pulled from the
GitHub Container Registry. See the Packages tab in the GitHub repo for the
latest version.

### General usage
Mount the log files of interest as `/access.log` and `/auth.log` in the Docker
container. Connect to the container on HTTP port 80 and the default interface
will be served. There is no security or SSL provided as this is primarily
intended as an auxilary container to be integrated with other containers and
hosted behind a reverse proxy, such as Traefik. Right now this only work with
CLF log files, but will eventually be made to work with at least standard auth
logs, as well.

### Use with `docker-compose`
Here is an example docker-compose.yml file showing how to integrate with a
reverse proxy (Traefik) to access logs for all proxy traffic. (Obviously, you'll
want to have other services as well which I haven't shown here.)
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
      - /var/run/docker.sock:/var/run/docker.sock
      - ./logs/:/logs/:rw
      - ./certs:/letsencrypt
      - ./traefik.yml:/etc/traefik.yml:ro
      - ./traefik:/etc/traefik
    depends_on:
      - logpager

  logpager:
    image: ghcr.io/jonbirge/logpager:dev
    labels:
      - "traefik.http.routers.logpagerdev.rule=Host(`$HOSTNAME`) && PathPrefix(`/logs`)"
      - "traefik.http.routers.logpagerdev.tls.certresolver=stackresolver"
      - "traefik.http.middlewares.striplogdev.stripprefix.prefixes=/logs/"
      - "traefik.http.routers.logpagerdev.middlewares=striplogdev"
    volumes:
      - /var/log/auth.log:/auth.log:ro
      - ./logs/access.log:/access.log:ro
      - ./logs/blacklist:/blacklist:rw

  # Other services...
```
