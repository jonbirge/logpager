let pollInterval;
let polling = false;
let page = 0;  // last page

// function to pull the log in JSON form from the server
function pollServer() {
    if (page < 0) {
        page = 0;  // reset page
    };
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
        updateHostNames();
    });
};

// function to take (n x 5) JSON array of strings and convert to HTML table,
// assuming the first row is table headers. in addition, make each IP address
// (the first element of each row) a link that will run a whois query on that IP
// using a JS function called whois().
function jsonToTable(json) {
    const data = JSON.parse(json);
    let table = '<table id="log-table">';
    
    // write table headers from first row
    table += '<tr>';
    for (let i = 0; i < data[0].length; i++) {
        table += '<th>' + data[0][i] + '</th>';
        if (i == 0) {
            table += '<th>Host name</th>'; // Add new header for Host name after the first header
        }
    }
    table += '</tr>';
    
    // write table rows from remaining rows
    for (let i = 1; i < data.length; i++) {
        table += '<tr>';
        for (let j = 0; j < data[i].length; j++) {
            if (j == 0) {
                // Add new cell for IP address after the first cell
                const ip = data[i][j];
                table += '<td><a href="#" onclick="whois(\'' + ip + '\')">' + ip + '</a></td>';
                // Add new cell for Host name after the first cell, assigning a random ID to the cell
                hostnameid = 'ip-' + Math.floor(Math.random() * 1000000);
                table += '<td id="' + hostnameid + '">-</td>';
                getHostName(hostnameid, ip);
            } else {
                table += '<td>' + data[i][j] + '</td>';
            }
        }
        table += '</tr>';
    }
    table += '</table>';

    return table;
};

function getHostName(hostnameid, ip) {
    // Get the host name from the IP address
    fetch('hostname.php?ip=' + ip)
    .then(response => response.text())
    .then(data => {
        // Update the cell with id hostnameid with the host name
        const hostnameCell = document.getElementById(hostnameid);
        hostnameCell.innerHTML = data;
    });
}

// function to run whois query on IP address string using the ARIN.net web service.
// the response is a JSON object containing the whois information.
function whois(ip) {
    const whoisDiv = document.getElementById('whois');
    whoisDiv.innerHTML = '<h2>Whois ' + ip + '...</h2>';
    fetch('https://whois.arin.net/rest/ip/' + ip + '.txt?showPocs=true')
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
};

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
};

// load the log on page load
window.onload = pollServer;
