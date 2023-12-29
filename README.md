# logpager

## About
Common Log Format web interface written using PHP and JS. Displays log events
as heatmap using tile plot generated with D3, allowing user to click on a given
period to drill down into the log. Performs asynchronous reverse-DNS
hostname resolution. User can click on IP address in log and see all events
from that IP, with a heatmap showing counts over time.

## Usage
Mount the log file of interest as `/access.log` in the Docker container. Connect
to the container on HTTP port 80 and the default interface will be served. There
is no security or SSL provided as this is primarily intended as an auxilary
container to be integrated with other containers and hosted behind a reverse
proxy, such as Traefik. Right now this only work with CLF log files, but will
eventually but made to work with at least standard auth logs, as well.

## Screenshot
![Screenshot 2023-12-29 032118](https://github.com/jonbirge/logpager/assets/660566/9923051b-8b13-4a74-bdbc-f5c9a46be3c4)

