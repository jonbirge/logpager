// global params
const params = new URLSearchParams(window.location.search);
const targetIP = params.get("ip");

function runScan(mode) {
    const uniqueID = Math.random().toString(36).substr(2, 9);
    const scanDiv = document.getElementById('scan');
    const scanButtonDiv = document.getElementById('scan-buttons');
    let initialButtons = scanButtonDiv.innerHTML;
    let scanPollInterval;
    let waitCount = 0;

    let scanURL;
    if (mode === 'deep') {
        scanURL = 'intel/startscan.php?ip=' + targetIP + '&id=' + uniqueID + '&mode=deep';
    } else {
        scanURL = 'intel/startscan.php?ip=' + targetIP + '&id=' + uniqueID + '&mode=quick';
    }
    console.log("runScan: " + scanURL);
    scanButtonDiv.innerHTML = "<p><b>Starting port scan...</b></p>";
    fetch(scanURL)
    .then(response => {
        if (response.ok) {
            scanPollInterval = setInterval(pollScanServer, 1000);
        } else {
            scanDiv.innerHTML = '<p>Error starting port scan script</p>';
        }
    });

    function pollScanServer() {
        // write message to scanButtonDiv each time we poll
        waitCount++;
        scanButtonDiv.innerHTML = "<p><b>Running port scan" + ".".repeat(waitCount % 4) + "</b></p>";

        fetch('intel/pollscan.php?id=' + uniqueID)
            .then(response => response.text())
            .then(data => {
                // Parsing JSON data
                var scanData = JSON.parse(data);
                var scanDone = false;

                // Check to see if the last element is the EOF token, and if it is, remove it
                if (scanData[scanData.length - 1] === "EOF") {
                    scanData.pop();
                    scanDone = true;
                    console.log("EOF encountered. Scan done.");
                }

                // Put the data into a <pre> tag inside the scanDiv
                scanDiv.innerHTML = "<pre>" + scanData.join("") + "</pre>";

                if (scanDone) {
                    clearInterval(scanPollInterval);
                    fetch('intel/cleanscan.php?id=' + uniqueID);
                    scanButtonDiv.innerHTML = initialButtons;
                }
            });
    }
}

function runPing() {
    const uniqueID = Math.random().toString(36).substr(2, 9);
    const pingDiv = document.getElementById('ping-button');
    const pingCanvas = document.getElementById('ping-chart');
    let pingPollInterval;

    // Add canvas to page
    pingCanvas.innerHTML = '<canvas id="pingChart" style="width: 80%"></canvas>';
    var ctx = document.getElementById('pingChart').getContext('2d');
    var pingChart = new Chart(ctx, {
        type: 'bar', // Using bar chart to represent histogram
        data: {
            labels: [], // Empty labels
            datasets: [{
                label: 'Ping Time Frequency',
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
        fetch('intel/pollping.php?id=' + uniqueID)
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

                // Calculate min and max ping values
                const minPing = Math.min(...pingData);
                const maxPing = Math.max(...pingData);
                const binCount = 15;
                const binSize = (maxPing - minPing) / binCount;

                // Create bins for histogram
                const histogram = new Array(binCount).fill(0);

                pingData.forEach(ping => {
                    const binIndex = Math.floor((ping - minPing) / binSize);
                    histogram[Math.min(binIndex, binCount - 1)]++;
                });

                // Preparing labels for each bin
                const labels = histogram.map((_, index) => {
                    const start = (minPing + index * binSize).toFixed(2);
                    const end = (minPing + (index + 1) * binSize).toFixed(2);
                    return `${start}-${end} ms`;
                });

                // Updating chart with new data
                pingChart.data.labels = labels;
                pingChart.data.datasets[0].data = histogram;
                pingChart.update();

                if (pingDone) {
                    clearInterval(pingPollInterval);
                    fetch('intel/cleanping.php?id=' + uniqueID);
                    pingDiv.innerHTML = "<p><button class='toggle-button' onclick='runPing()'>Run ping again</button></p>";
                }
            });
    }

    fetch('intel/startping.php?ip=' + targetIP + '&id=' + uniqueID)
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
    let waitCount = 0;

    function pollTraceServer() {
        // write message to traceButtonDiv each time we poll
        waitCount++;
        traceButtonDiv.innerHTML = "Running traceroute" + ".".repeat(waitCount % 4);

        fetch('intel/polltrace.php?id=' + uniqueID)
            .then(response => response.text())
            .then(data => {
                if (data.indexOf("END_OF_FILE") !== -1) {
                    clearInterval(tracePollInterval);
                    traceDiv.innerHTML = data;
                    traceButtonDiv.innerHTML = "<button class='toggle-button' onclick='runTrace()'>Run trace again</button>";
                    fetch('intel/cleantrace.php?id=' + uniqueID);
                } else {
                    traceDiv.innerHTML = data;
                }
            });
    }

    const traceURL = 'intel/starttrace.php?ip=' + targetIP + '&id=' + uniqueID;
    console.log(traceURL);
    fetch(traceURL)
        .then(response => {
            traceButtonDiv.innerHTML = "Running traceroute...";
            if (response.ok) {
                tracePollInterval = setInterval(pollTraceServer, 1000);
            } else {
                traceDiv.innerHTML = '<p>Error starting traceroute!</p>';
            }
        });
}

function runWhois() {
    const whoisDiv = document.getElementById("whois");
    fetch("intel/whois.php?ip=" + targetIP)
        .then((response) => response.text())
        .then((data) => {
            // remove whois button
            document.getElementById("whois-button").innerHTML = "";

            // output to whois div
            whoisDiv.innerHTML = data;
        });
}

function runAll() {
    runScan('deep');
    runPing();
    runTrace();
    runWhois(targetIP);
}

// Satellite imagery configuration
const satelliteConfig = {
    conus: {
        name: 'CONUS (USA)',
        geocolor: 'https://cdn.star.nesdis.noaa.gov/GOES16/ABI/CONUS/GEOCOLOR/latest.jpg',
        vis: 'https://cdn.star.nesdis.noaa.gov/GOES16/ABI/CONUS/02/latest.jpg',
        ir: 'https://cdn.star.nesdis.noaa.gov/GOES16/ABI/CONUS/13/latest.jpg',
        wv: 'https://cdn.star.nesdis.noaa.gov/GOES16/ABI/CONUS/09/latest.jpg',
        available: ['geocolor', 'vis', 'ir', 'wv']
    },
    mexico: {
        name: 'Mexico',
        geocolor: 'https://cdn.star.nesdis.noaa.gov/GOES16/ABI/SECTOR/mex/GEOCOLOR/latest.jpg',
        vis: 'https://cdn.star.nesdis.noaa.gov/GOES16/ABI/SECTOR/mex/02/latest.jpg',
        ir: 'https://cdn.star.nesdis.noaa.gov/GOES16/ABI/SECTOR/mex/13/latest.jpg',
        wv: 'https://cdn.star.nesdis.noaa.gov/GOES16/ABI/SECTOR/mex/09/latest.jpg',
        available: ['geocolor', 'vis', 'ir', 'wv']
    },
    canada: {
        name: 'Canada',
        geocolor: 'https://cdn.star.nesdis.noaa.gov/GOES16/ABI/SECTOR/can/GEOCOLOR/latest.jpg',
        vis: 'https://cdn.star.nesdis.noaa.gov/GOES16/ABI/SECTOR/can/02/latest.jpg',
        ir: 'https://cdn.star.nesdis.noaa.gov/GOES16/ABI/SECTOR/can/13/latest.jpg',
        wv: 'https://cdn.star.nesdis.noaa.gov/GOES16/ABI/SECTOR/can/09/latest.jpg',
        available: ['geocolor', 'vis', 'ir', 'wv']
    },
    europe: {
        name: 'Europe',
        geocolor: null,
        vis: 'https://eumetview.eumetsat.int/static-images/latestImages/EUMETSAT_MSG_RGBNatColourEnhncd_LowResolution_Europe.jpg',
        ir: 'https://eumetview.eumetsat.int/static-images/latestImages/EUMETSAT_MSG_IR108_LowResolution_Europe.jpg',
        wv: 'https://eumetview.eumetsat.int/static-images/latestImages/EUMETSAT_MSG_WV062_LowResolution_Europe.jpg',
        available: ['vis', 'ir', 'wv']
    },
    china: {
        name: 'China',
        geocolor: 'https://himawari8.nict.go.jp/img/D531106/latest.jpg',
        vis: 'https://himawari8.nict.go.jp/img/D531106/latest.jpg',
        ir: null,
        wv: null,
        available: ['geocolor', 'vis']
    }
};

let currentRegion = null;
let regionSelectDisabled = false;

function updateSatelliteRegion(region) {
    if (regionSelectDisabled) return;

    currentRegion = region;
    const config = satelliteConfig[region];

    // Update available image type options
    const imageTypeInputs = document.querySelectorAll('input[name="imageType"]');
    imageTypeInputs.forEach(input => {
        const imageType = input.value;
        const label = input.parentElement;

        if (config.available.includes(imageType)) {
            input.disabled = false;
            label.style.opacity = '1';
            label.style.cursor = 'pointer';
        } else {
            input.disabled = true;
            label.style.opacity = '0.4';
            label.style.cursor = 'not-allowed';
        }
    });

    // Select first available image type
    const firstAvailable = config.available[0];
    const firstInput = document.querySelector(`input[name="imageType"][value="${firstAvailable}"]`);
    if (firstInput) {
        firstInput.checked = true;
    }

    updateSatelliteImage();
}

function updateSatelliteImage() {
    if (!currentRegion) return;

    const selectedImageType = document.querySelector('input[name="imageType"]:checked');
    if (!selectedImageType) return;

    const imageType = selectedImageType.value;
    const config = satelliteConfig[currentRegion];
    const imageUrl = config[imageType];

    const container = document.getElementById('satellite-image-container');

    if (imageUrl) {
        // Add cache-busting parameter to ensure fresh image
        const cacheBuster = '?t=' + new Date().getTime();
        container.innerHTML = `
            <div style="position: relative;">
                <img src="${imageUrl}${cacheBuster}"
                     alt="${config.name} - ${imageType}"
                     style="max-width: 100%; height: auto; border-radius: 3px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);"
                     onerror="this.parentElement.innerHTML='<p style=\\'color: #d32f2f;\\'>Failed to load satellite image</p>'">
                <div style="margin-top: 10px; font-size: 0.9em; color: #666;">
                    ${config.name} - ${imageType.toUpperCase()}
                </div>
            </div>
        `;
    } else {
        container.innerHTML = `<p style="color: #ff9800;">This image type is not available for ${config.name}</p>`;
    }
}

function useCurrentLocation() {
    if (!navigator.geolocation) {
        alert('Geolocation is not supported by your browser');
        return;
    }

    const button = event.target;
    button.disabled = true;
    button.textContent = 'Getting location...';

    navigator.geolocation.getCurrentPosition(
        function(position) {
            const lat = position.coords.latitude;
            const lon = position.coords.longitude;

            // Determine region based on coordinates
            let detectedRegion = 'conus';

            // Simple region detection based on lat/lon bounds
            if (lat >= 24 && lat <= 50 && lon >= -125 && lon <= -66) {
                detectedRegion = 'conus';
            } else if (lat >= 14 && lat <= 33 && lon >= -118 && lon <= -86) {
                detectedRegion = 'mexico';
            } else if (lat >= 42 && lat <= 84 && lon >= -141 && lon <= -52) {
                detectedRegion = 'canada';
            } else if (lat >= 35 && lat <= 72 && lon >= -10 && lon <= 40) {
                detectedRegion = 'europe';
            } else if (lat >= 18 && lat <= 54 && lon >= 73 && lon <= 135) {
                detectedRegion = 'china';
            }

            // Disable region selector and select detected region
            regionSelectDisabled = true;
            const regionInputs = document.querySelectorAll('input[name="region"]');
            regionInputs.forEach(input => {
                input.disabled = true;
                if (input.value === detectedRegion) {
                    input.checked = true;
                }
            });

            updateSatelliteRegion(detectedRegion);

            button.textContent = 'Location detected: ' + satelliteConfig[detectedRegion].name;
            button.classList.add('disabled');
        },
        function(error) {
            alert('Unable to retrieve your location: ' + error.message);
            button.disabled = false;
            button.textContent = 'Use current location';
        },
        {
            timeout: 10000,
            enableHighAccuracy: false
        }
    );
}
