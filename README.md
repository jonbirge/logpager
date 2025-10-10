# log-pager

## Overview

Lightweight security log inspection and blacklisting web interface, intended to provide a dashboard for threats. Displays log events as heatmap using tile plot, allowing user to click on a given period to drill down into the log. Performs asynchronous geolocation and reverse DNS resolution.

Right now this will handle web logs, Traefik logs, and auth logs (e.g. sshd), but is designed to allow other log types to be added via a quasi-object-oriented programming interface.

The approach is to treat the log file itself as truth and run UNIX tool commands on the host (within a Docker container) to extract data from the log file directly, essentially running the kinds of local unix forensic commands a sysadmin would. This approach is intended to minimize the impact on the server, with no resources being used except when the web interface is actively being used.

## Demo

A public demo of the current development branch may (or may not) be running at <https://nyc.birgefuller.com/logs/>.

## Screenshots

### Default log display

![Screenshot 2024-05-19 165426](https://github.com/jonbirge/logpager/assets/660566/52c76b9b-dc43-480f-a568-02d4b393b41c)

### Search display

![Screenshot 2024-05-19 165513](https://github.com/jonbirge/logpager/assets/660566/4fb13ee7-2e25-4ef3-816d-0cb2d2919363)

### Usage

Mount your server's log files into the logpager container as shown below. Connect to the container on HTTP port 80 and the default interface will be served. There is no security or SSL provided as this is primarily intended as an auxilary container to be integrated with other containers and hosted behind a reverse proxy, such as Traefik.

### Docker Compose

The easiest way to use this is within an orchestrated set of containers that includes a reverse proxy and an SQL database (to handle the backlisting functionality). You can quickly stand up a fully functional instance of logpager using the `docker-compose.yml` file found in `/test/stack`.
