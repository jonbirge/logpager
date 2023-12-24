let pollInterval;
let polling = false;
let controller;
let fetchCount = 0;
let params = new URLSearchParams(window.location.search);
let page = params.get('page') !== null ? Number(params.get('page')) : 0;
let geolocate = true;
const apiWait = 250;  // ms to wait between external API calls

// pull the log in JSON form from the server
function pollServer() {
    // abort any pending fetches
    if (fetchCount > 0) {
        console.log('Aborting ' + fetchCount + ' fetches');
        controller.abort();
    }
    controller = new AbortController();
    fetchCount = 0;
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
    const signal = controller.signal;
    const searchInput = document.getElementById('search-input');
    const logDiv = document.getElementById('log');
    const search = searchInput.value;
    if (search == '') {
        console.log('search is empty');
    } else {
        console.log('searching for ' + search);
        fetchCount++;
        searchInput.value = 'Searching...';
        fetch('search.php?term=' + search, {signal})
        .then(response => response.text())
        .then(data => {
            fetchCount--;

            // write the search results to the log div
            const pageSpan = document.getElementById('page');
            logDiv.innerHTML = jsonToTable(data);
            pageSpan.innerHTML = '<b>Search results for ' + search + '</b>';
            searchInput.value = '';

            // disable all other buttons and 
            const buttons = document.querySelectorAll('button');
            buttons.forEach(button => {
                button.disabled = true;
                button.classList.add("disabled");
            });

            // enable search button
            const searchButton = document.getElementById('search-button');
            searchButton.disabled = false;
            searchButton.classList.remove("disabled");

            // add a reset button to the left of the search text box if it doesn't exist
            const resetButton = document.getElementById('reset-button');
            if (resetButton === null) {
                const resetButton = document.createElement('button');
                resetButton.id = 'reset-button';
                resetButton.innerHTML = 'Reset';
                resetButton.classList.add("toggle-button");
                resetButton.classList.add("gray");
                resetButton.onclick = resetSearch;
                const searchSpan = document.getElementById('search-span');
                searchSpan.insertBefore(resetButton, searchSpan.firstChild);
            } else {
                resetButton.disabled = false;
                resetButton.classList.remove("disabled");
            }
        });
    }
}

// reset search, re-enable all buttons and remove reset button
function resetSearch() {
    const signal = controller.signal;
    const logDiv = document.getElementById('log');
    const pageSpan = document.getElementById('page');
    const searchInput = document.getElementById('search-input');
    const searchButton = document.getElementById('search-button');
    const resetButton = document.getElementById('reset-button');
    const buttons = document.querySelectorAll('button');
    buttons.forEach(button => {
        button.disabled = false;
        button.classList.remove("disabled");
    });
    searchButton.innerHTML = 'Search';
    searchButton.onclick = doSearch;
    searchInput.value = '';
    resetButton.remove();
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
            table += '<th>Geolocation (courtesy of <a href=https://www.ip-api.com style="color: white">ip-api.com</a>)</th>';
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
            } else if (j == 2) {
                table += '<td class="request">' + data[i][j] + '</td>';
            } else if (j == 3) {
                const status = data[i][j];
                if (status == '200' || status == '304') {
                    table += '<td class="green">' + data[i][j] + '</td>';
                } else {
                    table += '<td class="red">' + data[i][j] + '</td>';
                }
            } else {
                table += '<td>' + data[i][j] + '</td>';
            }
        }
        table += '</tr>';
    }
    table += '</table>';

    // Get the host names from the IP addresses
    getHostNames(ips, signal);
    if (geolocate)
        getGeoLocations(ips, signal);

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
    let waitTime = 0;
    ips.forEach(ip => {
        setTimeout(() => {
        fetch('geo.php?ip=' + ip, {signal})
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
        }, waitTime, {signal});
        waitTime += apiWait;
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
    const uielements = [...document.querySelectorAll('button')];
    const textedit = document.getElementById('search-input');
    uielements.push(textedit);
    const watchButton = document.getElementById('watch-button');
    page = 0;  // reset page
    if (polling) {  // stop polling
        polling = false;
        clearInterval(pollInterval);
        watchButton.innerHTML = "Watch";
        watchButton.classList.remove("red");
        // enable all other ui elements
        uielements.forEach(uielement => {
            uielement.disabled = false;
            uielement.classList.remove("disabled");
        });
        pollServer();
    } else {
        pollServer();
        polling = true;
        pollInterval = setInterval(pollServer, 10000);
        // disable all other ui elements
        uielements.forEach(uielement => {
            uielement.disabled = true;
            uielement.classList.add("disabled");
        });
        // enable watch button
        watchButton.disabled = false;
        watchButton.classList.remove("disabled");
        watchButton.innerHTML = "Stop";
        watchButton.classList.add("red");
    };
}

// load the log on page load
window.onload = pollServer;
