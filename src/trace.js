// global params
let params = new URLSearchParams(window.location.search);
let targetIP = params.get('ip');

function runPing() {
    const uniqueID = Math.random().toString(36).substr(2, 9);
    const pingDiv = document.getElementById('ping-button');
    const pingCanvas = document.getElementById('ping-chart');
    let pingPollInterval;

    // Add canvas to page
    pingCanvas.innerHTML = '<canvas id="pingChart" style="width: 80%"></canvas>';
    var ctx = document.getElementById('pingChart').getContext('2d');
    var pingChart = new Chart(ctx, {
        type: 'line', // You can change this to 'bar' if you prefer a bar chart
        data: {
            labels: [], // Empty labels
            datasets: [{
                label: 'Ping Time (ms)',
                data: [], // Empty data
                backgroundColor: 'rgba(0, 123, 255, 0.5)',
                borderColor: 'rgba(0, 123, 255, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    function pollPingServer() {
        fetch('pollping.php?id=' + uniqueID)
            .then(response => response.text())
            .then(data => {
                // Parsing JSON data
                var pingData = JSON.parse(data);
                var pingDone = false;

                // Check to see if the last element is -1, and if it is, remove it
                if (pingData[pingData.length - 1] === -1) {
                    pingData.pop();
                    pingDone = true;
                    console.log("Ping done!");
                }

                // Preparing labels for each data point (assuming sequential labels)
                var labels = pingData.map((_, index) => `Ping ${index + 1}`);

                // Updating chart with new data
                pingChart.data.labels = labels;
                pingChart.data.datasets[0].data = pingData;
                pingChart.update();

                if (pingDone) {
                    clearInterval(pingPollInterval);
                    fetch('cleanping.php?id=' + uniqueID);
                    pingDiv.innerHTML = "<p><button class='toggle-button' onclick='runPing()'>Run ping again</button></p>";
                }
            });
    }

    fetch('startping.php?ip=' + targetIP + '&id=' + uniqueID)
        .then(response => {
            pingDiv.innerHTML = "<p>Running ping...</p>";
            if (response.ok) {
                pingPollInterval = setInterval(pollPingServer, 1000);
            } else {
                pingDiv.innerHTML = '<p>Error starting ping script</p>';
            }
        });
}

function runTrace() {
    const uniqueID = Math.random().toString(36).substr(2, 9);
    const traceDiv = document.getElementById('trace');
    const traceButtonDiv = document.getElementById('trace-button');
    let tracePollInterval;

    function pollTraceServer() {
        fetch('polltrace.php?id=' + uniqueID)
            .then(response => response.text())
            .then(data => {
                if (data.indexOf("END_OF_FILE") !== -1) {
                    clearInterval(tracePollInterval);
                    traceDiv.innerHTML = data;
                    //traceButtonDiv.innerHTML = "<p><button class='toggle-button' onclick='runTrace()'>Run trace again</button></p>";
                    //fetch('cleantrace.php?id=' + uniqueID);
                } else {
                    traceDiv.innerHTML = data;
                }
            });
    }

    const traceURL = 'starttrace.php?ip=' + targetIP + '&id=' + uniqueID;
    console.log(traceURL);
    fetch(traceURL)
        .then(response => {
            traceButtonDiv.innerHTML = "<p>Running traceroute...</p>";
            if (response.ok) {
                tracePollInterval = setInterval(pollTraceServer, 1000);
            } else {
                traceDiv.innerHTML = '<p>Error starting traceroute!</p>';
            }
        });
}

function runAll() {
    runPing();
    runTrace();
}
