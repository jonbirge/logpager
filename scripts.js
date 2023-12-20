let pollInterval;
let polling = false;
let page = 0;  // last page

function pollServer() {
    if (page < 0) {
        page = 0;  // reset page
    };
    const logDiv = document.getElementById('log');
    fetch('logtail.php?page=' + page)
        .then(response => response.text())
        .then(data => {
            logDiv.innerHTML = data;
            const pageSpan = document.getElementById('page');
            if (page == 0) {
                pageSpan.innerHTML = "Last page";
            } else {
                pageSpan.innerHTML = "Page " + page + " from end";
            }
        });
};

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

window.onload = pollServer;