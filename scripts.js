let pollInterval;
let polling = false;
let page = 0;  // last page

// function to pull the log in JSON form from the server
function pollServer() {
    if (page < 0) {
        page = 0;  // reset page
    };
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

// function to take [n by 5] JSON array of strings and convert to HTML table, assuming first row is header
function jsonToTable(json) {
    let table = "<table>";
    let data = JSON.parse(json);
    let header = data[0];
    let rows = data.slice(1);
    table += "<tr>";
    header.forEach(function (cell) {
        table += "<th>" + cell + "</th>";
    });
    table += "</tr>";
    rows.forEach(function (row) {
        table += "<tr>";
        row.forEach(function (cell) {
            table += "<td>" + cell + "</td>";
        });
        table += "</tr>";
    });
    table += "</table>";
    return table;
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
