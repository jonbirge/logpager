# log-pager

## About
Common Log Format paging web interface written using PHP and JS. Performs asynchronous reverse-DNS hostname resolution,
and provides on-demand whois of any IP address in the log.

## Usage
Mount the log file of interest as `/access.log` in the Docker container. Connect to the container on HTTP port 80 and the
default interface will be served. There is no security or SSL provided as this is primarily intended as an auxilary container
to be integrated with other containers and hosted behind a reverse proxy, such as Traefik.

## Screenshot
![Screenshot 2023-12-22 195331](https://github.com/jonbirge/logpager/assets/660566/e7fba02f-162e-40a1-9db5-1068c20e359c)
