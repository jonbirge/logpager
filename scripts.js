let pollInterval;
let polling = false;
let controller;
let fetchCount = 0;
let params = new URLSearchParams(window.location.search);
let page = params.get('page') !== null ? Number(params.get('page')) : 0;
const geolocate = true;

// pull the log in JSON form from the server
function pollServer() {
    // abort any pending fetches
    if (fetchCount > 0) {
        controller.abort();
        fetchCount = 0;
    }
    controller = new AbortController();
    if (page < 0) {
        page = 0;  // reset page
    };

    // update the page number in the URL
    const url = new URL(window.location.href);
    url.searchParams.set('page', page);
    window.history.replaceState({}, '', url);

    // get the log from the server
    const whoisDiv = document.getElementById('whois');
    whoisDiv.innerHTML = '';
    fetch('logtail.php?page=' + page)
    .then(response => response.text())
    .then(data => {
        const logDiv = document.getElementById('log');
        const pageSpan = document.getElementById('page');
        logDiv.innerHTML = jsonToTable(data);
        if (page == 0) {
            pageSpan.innerHTML = "Last page";
        } else {
            pageSpan.innerHTML = "Page " + page + " from end";
        }
    });
}

// do search on log
function doSearch() {
    const searchInput = document.getElementById('search-input');
    const search = searchInput.value;
    if (search == '') {
        console.log('search is empty');
    } else {
        console.log('searching for ' + search);
        fetch('search.php?term=' + search)
        .then(response => response.text())
        .then(data => {
            // write the search results to the log div
            const logDiv = document.getElementById('log');
            const pageSpan = document.getElementById('page');
            logDiv.innerHTML = jsonToTable(data);
            pageSpan.innerHTML = 'Search for ' + search + '...';

            // disable all other buttons and 
            const buttons = document.querySelectorAll('button');
            buttons.forEach(button => {
                button.disabled = true;
                button.classList.add("disabled");
            });

            // turn the search button into a reset button and enable
            const searchButton = document.getElementById('search-button');
            searchButton.innerHTML = 'Reset';
            searchButton.onclick = resetSearch;
            searchButton.disabled = false;
            searchButton.classList.remove("disabled");
        });
    }
}

// reset search
function resetSearch() {
    const searchInput = document.getElementById('search-input');
    searchInput.value = '';
    const searchButton = document.getElementById('search-button');
    searchButton.innerHTML = 'Search';
    searchButton.onclick = doSearch;
    const pageSpan = document.getElementById('page');
    pageSpan.innerHTML = '';

    // enable all other buttons
    const buttons = document.querySelectorAll('button');
    buttons.forEach(button => {
        button.disabled = false;
        button.classList.remove("disabled");
    });

    pollServer();
}

// take n x 5 JSON array of strings and convert to HTML table, assuming the
// first row is table headers. write table to div.
function jsonToTable(json) {
    const signal = controller.signal;
    let ips = [];
    const data = JSON.parse(json);
    let table = '<table id="log-table">';

    // write table headers from first row
    table += '<tr>';
    for (let i = 0; i < data[0].length; i++) {
        table += '<th>' + data[0][i] + '</th>';
        if (i == 0) {
            table += '<th>Host name</th>';
            table += '<th>Geolocation (courtesy of <a href=ip-api.com style="color: white">ip-api.com</a>)</th>';
        }
    }
    table += '</tr>';
    
    // write table rows from remaining rows
    for (let i = 1; i < data.length; i++) {
        table += '<tr>';
        for (let j = 0; j < data[i].length; j++) {
            if (j == 0) {
                const ip = data[i][j];
                ips.push(ip);
                // Add new cell for IP address after the first cell
                table += '<td><a href="#" onclick="whois(\'' + ip + '\')">' + ip + '</a></td>';
                // Add new cell for Host name after the first cell
                hostnameid = 'hostname-' + ip;
                table += '<td id="' + hostnameid + '">-</td>';
                // Add new cell for Geolocation after the first cell
                geoid = 'geo-' + ip;
                table += '<td id="' + geoid + '">-</td>';
            } else if (j == 3) {
                const status = data[i][j];
                if (status == '404') {
                    table += '<td class="red">' + data[i][j] + '</td>';
                } else {
                    table += '<td class="green">' + data[i][j] + '</td>';
                }
            } else {
                table += '<td>' + data[i][j] + '</td>';
            }
        }
        table += '</tr>';
    }
    table += '</table>';

    // Get the host names from the IP addresses
    if (!polling) {
        getHostNames(ips, signal);
        if (geolocate)
            getGeoLocations(ips, signal);
    }

    return table;
}

// get host names from IP addresses
function getHostNames(ips, signal) {
    // Get set of unique ip addresses
    ips = [...new Set(ips)];
    console.log('Getting host names for ' + ips);
    fetchCount++;
    // Grab each ip address and send to rdns.php
    ips.forEach(ip => {
        fetch('rdns.php?ip=' + ip, {signal})
        .then(response => response.text())
        .then(data => {
            // Update the cell with id hostnameid with the hostname
            const hostnameid = 'hostname-' + ip;
            // Get all cells with id of the form hostname-ip
            const hostnameCells = document.querySelectorAll('[id^="hostname-' + ip + '"]');
            // set each cell in hostnameCells to data
            hostnameCells.forEach(cell => {
                cell.innerHTML = data;
            });
            fetchCount--;
        })
        .catch(error => {
            if (error.name === 'AbortError') {
              console.log('Fetch safely aborted');
            } else {
              console.error('Fetch error:', error);
            }
        });
    });
}

// get geolocations from IP addresses using ip-api.com
function getGeoLocations(ips, signal) {
    // Get set of unique ip addresses
    ips = [...new Set(ips)];
    console.log('Getting geolocations for ' + ips);
    fetchCount++;
    // Grab each ip address and send to ip-api.com
    ips.forEach(ip => {
        fetch('http://ip-api.com/json/' + ip, {signal})
        .then(response => response.json())
        .then(data => {
            // Update the cell with id geoid with the geolocation
            const geoid = 'geo-' + ip;
            // Get all cells with id of the form geo-ipAddress
            const geoCells = document.querySelectorAll('[id^="geo-' + ip + '"]');
            // set each cell in geoCells to data
            geoCells.forEach(cell => {
                cell.innerHTML = data.country + ', ' + data.regionName + ', ' + data.city;
            });
            fetchCount--;
        })
        .catch(error => {
            if (error.name === 'AbortError') {
              console.log('Fetch safely aborted');
            } else {
              console.error('Fetch error:', error);
            }
        });
    });
}

// run whois query on IP address string using the ARIN.net web service. the
// response is a JSON object containing the whois information.
function whois(ip) {
    const whoisDiv = document.getElementById('whois');
    whoisDiv.innerHTML = '<h2>Whois ' + ip + '...</h2>';
    fetch('whois.php?ip=' + ip)
    .then(response => response.text())
    .then(data => {
        // remove comment lines from whois data
        data = data.replace(/^#.*$/gm, '');
        
        // remove all blank lines from whois data
        data = data.replace(/^\s*[\r\n]/gm, '');

        // output to whois div
        whoisHTML = '<h2>Whois ' + ip + '</h2>';
        whoisHTML += '<pre>';
        whoisHTML += data;
        whoisHTML += '</pre>';
        whoisDiv.innerHTML = whoisHTML;
    });
}

// function to setup polling
function runWatch() {
    const watchButton = document.getElementById('watch-button');
    page = 0;  // reset page
    if (polling) {
        polling = false;
        clearInterval(pollInterval);
        watchButton.innerHTML = "Watch";
        watchButton.classList.remove("red");
    } else {
        pollServer();
        polling = true;
        pollInterval = setInterval(pollServer, 10000);
        watchButton.innerHTML = "Stop";
        watchButton.classList.add("red");
    };
}

// load the log on page load
window.onload = pollServer;
