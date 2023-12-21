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
    });
};

// function to take (n x 5) JSON array of strings and convert to HTML table,
// assuming the first row is table headers. in addition, make each IP
// address (the first element of each row) a link that will run a whois query on
// that IP using a JS function called whois().
function jsonToTable(json) {
    const data = JSON.parse(json);
    let table = '<table>';
    
    // write table headers from first row
    table += '<tr>';
    for (let i = 0; i < data[0].length; i++) {
        table += '<th>' + data[0][i] + '</th>';
    }
    
    // write table rows from remaining rows
    for (let i = 1; i < data.length; i++) {
        table += '<tr>';
        for (let j = 0; j < data[i].length; j++) {
            if (j == 0) {
                table += '<td><a href="#" onclick="whois(\'' + data[i][j] + '\')">' + data[i][j] + '</a></td>';
            } else {
                table += '<td>' + data[i][j] + '</td>';
            }
        }
        table += '</tr>';
    }
    table += '</table>';

    return table;
};

// function to run whois query on IP address string using the ARIN.net web service.
// the response is a JSON object containing the whois information.
function whois(ip) {
    fetch('https://whois.arin.net/rest/ip/' + ip + '.txt')
    .then(response => response.text())
    .then(data => {
        const whoisDiv = document.getElementById('whois');
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
