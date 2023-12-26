// settings
const geolocate = false;
const apiWait = 250;  // ms to wait between external API calls

// global variables
let pollInterval;
let polling = false;
let controller;
let fetchCount = 0;
let params = new URLSearchParams(window.location.search);
let page = params.get('page') !== null ? Number(params.get('page')) : 0;
let search = params.get('search');

// decide what to do on page load
if (search !== null) {  // search beats page
    console.log('on page load: searching for ' + search + '...');
    window.onload = doSearch;
} else {
    console.log('on page load: loading logs...');

    // on window load run pollServer and plotHeatmap
    window.onload = () => {
        pollServer();
        plotHeatmap();
    };
}

// pull the log in JSON form from the server
function pollServer() {
    console.log('pollServer: fetching page ' + page);

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

    // remove search term from URL and update page numbers
    const url = new URL(window.location.href);
    url.searchParams.delete('search');
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

// plot heatmap of log entries by hour and day
function plotHeatmap() {
    console.log('plotHeatmap: plotting heatmap');

    // get summary data (2D map of log entry counts referenced by day-of-the-year and hour)
    fetch('heatmap.php')
        .then(response => response.json())
        .then(jsonData => {

            // Process the data to fill in missing hours with zero counts
            const processedData = [];
            Object.keys(jsonData).forEach(date => {
                for (let hour = 1; hour <= 24; hour++) {
                    processedData.push({
                        date: date,
                        hour: hour,
                        count: jsonData[date][hour] || 0 // Use zero if the hour is missing
                    });
                }
            });

            // Set dimensions for the heatmap
            const cellSize = 10; // size of each tile
            const ratio = 3; // width to height ratio
            const margin = { top: 50, right: 20, bottom: 50, left: 60 };
            const width = ratio * Object.keys(jsonData).length * cellSize;
            const height = 24 * cellSize; // 24 hours

            // Color scale
            const colorScale = d3.scaleSequential(d3.interpolateInferno)
                .domain([0, d3.max(processedData, d => d.count)]);

            // Creating scales for the axes
            const xScale = d3.scaleBand()
                .domain(Object.keys(jsonData))
                .range([0, width]);

            const yScale = d3.scaleBand()
                .domain(d3.range(1, 25))
                .range([0, height]);

            // Create SVG element
            const svg = d3.select('#heatmap')
                .append('svg')
                .attr('width', width + margin.left + margin.right)
                .attr('height', height + margin.top + margin.bottom)
                .append('g')
                .attr('transform', `translate(${margin.left},${margin.top})`);

            // Creating the tiles
            svg.selectAll()
                .data(processedData)
                .enter()
                .append('rect')
                .attr('x', d => xScale(d.date))
                .attr('y', d => yScale(d.hour))
                .attr('width', xScale.bandwidth())
                .attr('height', yScale.bandwidth())
                .style('fill', d => colorScale(d.count))
                .on('click', function(d) {
                    // get the date and hour from the data
                    const date = d.date;
                    const hour = d.hour;
                    // build a partial date and time string for search
                    const partial = date + ' ' + hour + ':';
                    console.log('plotHeatmap: searching for ' + partial);
                });

            // Add X-axis
            svg.append('g')
                .attr('transform', `translate(0,${height})`)
                .call(d3.axisBottom(xScale).tickValues(xScale.domain().filter(function (d, i) { return !(i % 5); }))); // Adjust the tick interval as needed

            // Add Y-axis
            svg.append('g')
                .call(d3.axisLeft(yScale));

            // Center the chart in the div
            d3.select('#heatmap')
                .style('display', 'flex')
                .style('justify-content', 'center')
                .style('align-items', 'center');

            // Add title
            svg.append('text')
                .attr('x', width / 2)
                .attr('y', -20)
                .attr('text-anchor', 'middle')
                .style('font-size', '16px')
                .text('Log entry counts per hour');

            // Add X-axis label
            svg.append('text')
                .attr('x', width / 2)
                .attr('y', height + 40)
                .attr('text-anchor', 'middle')
                .style('font-size', '12px')
                .text('Day of the year');

            // Add Y-axis label
            svg.append('text')
                .attr('x', -(height / 2))
                .attr('y', -40)
                .attr('text-anchor', 'middle')
                .attr('transform', 'rotate(-90)')
                .style('font-size', '12px')
                .text('Hour of the day');

        });
}

// uiSearch is called when the search button is clicked
function uiSearch() {
    const searchButton = document.getElementById('search-button');
    const searchInput = document.getElementById('search-input');
    search = searchInput.value;
    console.log('uiSearch: searching for ' + search);

    // add search term to URL
    const url = new URL(window.location.href);
    url.searchParams.set('search', search);
    url.searchParams.delete('page');
    window.history.replaceState({}, '', url);

    doSearch();
}

// do search on log
function doSearch() {
    const searchInput = document.getElementById('search-input');
    searchInput.value = search;  // handle case where search is set by URL
    const logDiv = document.getElementById('log');

    console.log('doSearch: searching for ' + search);

    // abort any pending fetches
    if (fetchCount > 0) {
        console.log('Aborting ' + fetchCount + ' fetches');
        controller.abort();
    }
    controller = new AbortController();
    fetchCount = 0;

    // remove page parameter from URL
    const url = new URL(window.location.href);
    url.searchParams.delete('page');
    window.history.replaceState({}, '', url);

    if (search == '') {
        console.log('search is empty');
    } else {
        fetch('search.php?term=' + search)
            .then(response => response.text())
            .then(data => {
                // write the search results to the log div
                const pageSpan = document.getElementById('page');
                logDiv.innerHTML = jsonToTable(data);
                pageSpan.innerHTML = '<b>Search results for ' + search + '</b>';

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
    // searchButton.onclick = uiSearch;
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
            if (j == 0) {  // ip address
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
            } else if (j == 1) {  // timestamp
                // remove the timezone from the timestamp
                const timestamp = data[i][j].replace(/\s.*$/, '');
                table += '<td>' + timestamp + '</td>';
            } else if (j == 2) {  // request
                table += '<td class="request">' + data[i][j] + '</td>';
            } else if (j == 3) {  // status
                const status = data[i][j];
                if (status == '200' || status == '304') {
                    table += '<td class="green">' + data[i][j] + '</td>';
                } else {
                    table += '<td class="red">' + data[i][j] + '</td>';
                }
            } else {  // anything else
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
        fetch('rdns.php?ip=' + ip, { signal })
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
            fetch('geo.php?ip=' + ip, { signal })
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
        }, waitTime, { signal });
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
            whoisHTML += data;
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
