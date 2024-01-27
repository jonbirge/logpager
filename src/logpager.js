// hard-wired settings
const geolocate = true; // pull IP geolocation from external service?
const hostNames = true; // pull hostnames from external service?
const orgNames = true; // pull organization names from external service?
const tileLabels = false; // show tile labels on heatmap?
const apiWait = 200; // milliseconds to wait between external API calls
const maxRequestLength = 36; // truncation length of log details

// global variables
let pollInterval;
let polling = false;
let controller;
let fetchCount = 0;
let params = new URLSearchParams(window.location.search);
let page = params.get("page") !== null ? Number(params.get("page")) : 0;
let search = params.get("search");
let logType = params.get("type") !== null ? params.get("type") : "clf";  // "clf" or "auth"
let tableLength = 0;  // used to decide when to reuse the table
let geoCache = {};  // cache of geolocation data
let hostnameCache = {};  // cache of hostnames

// get list of logs present on server and enable or disable tabs
fetch("manifest.php")
    .then((response) => response.json())
    .then((data) => {
        const haveCLF = data.includes("access.log");
        const haveAuth = data.includes("auth.log");
        if (!haveCLF) {
            document.getElementById("clftab").style.display = 'none';
            logType = "auth";  // because clf is default
        } else {
            document.getElementById("clftab").style.display = '';
        }
        if (!haveAuth) {
            document.getElementById("authtab").style.display = 'none';
        } else {
            document.getElementById("authtab").style.display = '';
        }
        // highlight the current log type
        if (logType == "clf") {
            document.getElementById("clftab").classList.add("selected");
            document.getElementById("authtab").classList.remove("selected");
        } else {
            document.getElementById("authtab").classList.add("selected");
            document.getElementById("clftab").classList.remove("selected");
        }
        console.log("logType: " + logType);
    });

// decide what to do on page load
if (search !== null) {  // search beats page
    console.log("page load: searching for " + search + "...");
    window.onload = doSearch;
} else {
    console.log("page load: loading " + logType + " log...");
    // on window load run pollServer() and plotHeatmap()
    window.onload = () => {
        pollLog();
        plotHeatmap();
    };
}

// update time sensitive elements every second
function updateClock() {
    // find all elements with id of the form timestamp:*
    const timestampElements = document.querySelectorAll('[id^="timestamp:"]');

    // update each timestamp element
    timestampElements.forEach((element) => {
        const timestamp = element.id.replace("timestamp:", "");
        const dateObj = new Date(timestamp);
        const timediff = timeDiff(dateObj, new Date());
        element.innerHTML = timediff;
    });
}
updateClock();
setInterval(updateClock, 1000);

// create a Date object from a log timestamp of the form DD/Mon/YYYY:HH:MM:SS, assuming UTC timezone
function parseCLFDate(clfstamp) {
    const parts = clfstamp.split(/[:/]/); // split on : and /
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const monthIndex = months.indexOf(parts[1]);
    const dateObj = new Date(Date.UTC(parts[2], monthIndex, parts[0], parts[3], parts[4], parts[5]));
    return dateObj;
}

// take two Date objects and return the difference in time in simple human-readable terms, such as "3 days" or "5 seconds"
function timeDiff(date1, date2) {
    const diff = date2 - date1;
    const seconds = Math.floor(diff / 1000);
    const minutes = Math.floor(seconds / 60);
    const hours = Math.floor(minutes / 60);
    const days = Math.floor(hours / 24);
    if (days > 2) {
        return days + " days";
    } else if (hours > 2) {
        return hours + " hrs";
    } else if (minutes > 5) {
        return minutes + " min";
    } else {
        return seconds + " sec";
    }
}

// pull the relevent log data from the server
function pollLog() {
    console.log("pollLog: fetching page " + page + " of type " + logType);

    // abort any pending fetches
    if (fetchCount > 0) {
        console.log("Aborting " + fetchCount + " fetches");
        controller.abort();
    }
    controller = new AbortController();
    fetchCount = 0;
    if (page < 0) {
        page = 0; // reset page
    }

    // reset the URL
    const url = new URL(window.location.href);
    url.searchParams.delete("search");
    url.searchParams.set("type", logType);
    url.searchParams.set("page", page);
    window.history.replaceState({}, "", url);

    // clear whois and status divs
    const whoisDiv = document.getElementById("whois");
    whoisDiv.innerHTML = "";
    const searchStatus = document.getElementById("status");
    searchStatus.innerHTML = "";

    // get the log from the server
    fetch("logtail.php?type=" + logType + "&page=" + page)
        .then((response) => response.text())
        .then((data) => {
            const pageSpan = document.getElementById("page");
            updateTable(data);
            if (page == 0) {
                pageSpan.innerHTML = "Last page";
            } else {
                pageSpan.innerHTML = "Page " + page + " from end";
            }
        });
}

// plot heatmap of log entries by hour and day
function plotHeatmap(searchTerm) {
    console.log("plotHeatmap: plotting heatmap");

    // Build data query URL
    let heatmapURL;
    if (logType == "clf") {
        heatmapURL = "clfheatmap.php";
    } else {
        heatmapURL = "authheatmap.php";
    }
    if (searchTerm) {
        heatmapURL += "?search=" + searchTerm;
    }

    // get summary data from server
    console.log("plotHeatmap: fetching " + heatmapURL);
    fetch(heatmapURL)
        .then( (response) => response.json() )
        .then(jsonToHeatmap);
}

// Take JSON array of commond log data and write HTML table
function updateTable(jsonData) {
    const data = JSON.parse(jsonData);
    const logDiv = document.getElementById("log");
    const signal = controller.signal;
    let ips = [];
    let row;

    // check to see if the table needs to be rebuilt
    if (data.length != tableLength) {
        console.log("updateTable: rebuilding table");
        tableLength = data.length;
        let table0 = '<table id="log-table" class="log-table">';
        for (let i = 0; i < data.length; i++) {
            table0 += '<tr id="row-' + i + '"></tr>';
        }
        table0 += "</table>";
        logDiv.innerHTML = table0;
    }

    // write table headers from first row
    let headrow = document.getElementById("row-0");
    row = "";
    for (let i = 0; i < data[0].length; i++) {
        if (i == 0) {
            row += "<th>" + data[0][i] + "</th>";
            if (hostNames) {
                row += '<th class="hideable">Domain name</th>';
            }
            if (orgNames) {
                row += '<th class="hideable">Organization</th>';
            }
            if (geolocate) {
                row +=
                '<th>Geolocation<br>(from <a href=https://www.ip-api.com style="color: white">ip-api</a>)</th>';
            }
        } else if (i == 2) {  // details
            row += '<th class="hideable">' + data[0][i] + '</th>';
        } else {
            row += "<th>" + data[0][i] + "</th>";
        }
    }
    headrow.innerHTML = row;

    // write table rows from remaining rows
    for (let i = 1; i < data.length; i++) {
        rowElement = document.getElementById("row-" + i);
        row = "";
        for (let j = 0; j < data[i].length; j++) {
            if (j == 0) {
                // ip address
                const ip = data[i][j];
                ips.push(ip);
                // Add cell for IP address with link to search for ip address
                const srchlink = "?type=" + logType + "&search=ip:" + ip;
                row += "<td><a href=" + srchlink + ">" + ip + "</a><br>";
                // Create link string that calls blacklist(ip) function
                const blacklistCall = 'onclick="blacklist(' + "'" + ip + "'" + '); return false"';
                row += '<a class="blue" href="#" ' + blacklistCall + ">blacklist</a>";
                // Create link string that calls whois(ip) function
                const whoisCall = 'onclick="whois(' + "'" + ip + "'" + '); return false"';
                row += ' <a class="blue" href="#" ' + whoisCall + ">whois</a></td>";
                // Add new cell for Host name after the first cell
                if (hostNames) {
                    const hostnameid = "hostname-" + ip;
                    row += '<td class="hideable" id="' + hostnameid + '">-</td>';
                }
                // Add new cell for Organization name after the first cell
                if (orgNames) {
                    const orgid = "org-" + ip;
                    row += '<td class="hideable" id="' + orgid + '">-</td>';
                }
                // Add new cell for Geolocation after the first cell (maybe)
                if (geolocate) {
                    const geoid = "geo-" + ip;
                    row += '<td id="' + geoid + '">-</td>';
                }
            } else if (j == 1) {
                const clfStamp = data[i][j].replace(/\s.*$/, "");  // remove the timezone
                const dateStamp = parseCLFDate(clfStamp);  // assume UTC
                const timediff = timeDiff(dateStamp, new Date());
                const jsonDate = dateStamp.toJSON();
                row += "<td id=timestamp:" + jsonDate + ">"; 
                row += timediff + "</td>";
            } else if (j == 2) {
                // request
                const rawRequest = data[i][j];
                // truncate request to 32 characters
                const truncRequest =
                    rawRequest.length > maxRequestLength
                        ? rawRequest.substring(0, maxRequestLength) + "..."
                        : rawRequest;
                row += '<td class="code hideable">' + truncRequest + "</td>";
            } else if (j == 3) {
                // common status handling
                const greenStatus = ["200", "304", "OK"];
                const redStatus = ["308", "400", "401", "403", "404", "500", "FAIL"];
                const status = data[i][j];
                if (greenStatus.includes(status)) {
                    row += '<td class="green">' + status + "</td>";
                } else if (redStatus.includes(status)) {
                    row += '<td class="red">' + status + "</td>";
                } else {
                    row += '<td class="gray">' + status + "</td>";
                } 
            } else {
                // anything else
                row += "<td>" + data[i][j] + "</td>";
            }
        }
        rowElement.innerHTML = row;
    }

    // Get the host names from the IP addresses
    const ipSet = [...new Set(ips)]; // Get unique IP addresses
    if (hostNames) getHostNames(ipSet, signal);
    if (geolocate | orgNames) getGeoLocations(ipSet, signal);
}

// Function to send POST request to blacklist.php with a given IP address in the body of the POST
function blacklist(ip) {
    console.log("blacklist: add " + ip);
    var formData = new FormData();
    formData.append('ip', ip);
    fetch("blacklist.php", {
        method: "POST",
        body: formData,
    })
        .then((response) => response.text())
        .then((data) => {
            const status = document.getElementById("status");
            status.innerHTML = data;
        });
}

// Take JSON array of command log data and build SVG heatmap
function jsonToHeatmap(jsonData) {
    // Check if SVG element already exists and remove if so
    const svgElement = document.querySelector("svg");
    if (svgElement) {
        svgElement.remove();
    }
    
    // Process the data to work with D3 library
    let processedData = [];
    Object.keys(jsonData).forEach((date) => {
        for (let hour = 0; hour < 24; hour++) {
            const hourStr = hour.toString().padStart(2, "0");
            processedData.push({
                date: date,
                hour: hourStr,
                count:
                    jsonData[date][hourStr] !== undefined
                        ? jsonData[date][hourStr]
                        : null,
            });
        }
    });

    // Remove null values from the data
    processedData = processedData.filter((d) => d.count !== null);

    // Set dimensions for the heatmap
    const cellSize = 11; // size of each tile
    const ratio = 1; // width to height ratio
    const margin = { top: 25, right: 50, bottom: 50, left: 50 };
    const width = ratio * Object.keys(jsonData).length * cellSize;
    const height = 24 * cellSize;  // 24 hours

    // Creating scales for date axes
    const xScale = d3
        .scaleBand()
        .domain(Object.keys(jsonData))
        .range([0, width]);

    // Create array of hour label strings with leading zeros
    const hours = [];
    for (let i = 0; i < 24; i++) {
        hours.push(i.toString().padStart(2, "0"));
    }

    // Create d3 scale for hour axis as string categories from hours array
    const yScale = d3
        .scaleBand()
        .domain(hours)
        .range([0, height]);

    // Create SVG element
    const svg = d3
        .select("#heatmap")
        .append("svg")
        .attr("font-size", "12px")
        .attr("width", "100%") // Set width to 100%
        .style("height", height + "px") // Set height using CSS
        .attr(
            "viewBox",
            `${-margin.left} 0 ${width + margin.right + margin.left + 25} ${height + margin.bottom + margin.top
            }`
        ) // Add viewBox
        .append("g")
        .attr("transform", `translate(0,${margin.top})`);

    // Create color scale
    const colorScale = d3
        .scaleSqrt()
        .interpolate(() => d3.interpolatePlasma)
        .domain([1, d3.max(processedData, (d) => d.count)])
        .range([0, 1]);

    // Create the tiles and make interactive
    svg.selectAll()
        .data(processedData)
        .enter()
        .append("rect")
        .attr("x", (d) => xScale(d.date))
        .attr("y", (d) => yScale(d.hour))
        .attr("width", xScale.bandwidth() - 1) // create a gap between tiles
        .attr("height", yScale.bandwidth() - 1) // create a gap between tiles
        .style("fill", (d) => colorScale(d.count))
        .on("click", function (d) {
            // get the date and hour from the data
            const date = d.date;
            const hour = d.hour;
            // build a partial date and time string for search
            const partial = date + " " + hour + ":";
            const searchTerm = "date:" + buildTimestampSearch(date, hour);
            console.log("plotHeatmap: searching for " + searchTerm);
            // update the search box
            const searchInput = document.getElementById("search-input");
            searchInput.value = searchTerm;
            // run the search
            uiSearch();
        });

    // Add legend
    const legendWidth = 13;
    const legend = svg
        .selectAll(".legend")
        .data(colorScale.ticks(15))
        .enter()
        .append("g")
        .attr("class", "legend")
        .attr("width", "10%")
        .attr("transform", (d, i) => {
            return `translate(${width + 20}, ${i * legendWidth})`;
        });

    // Add rectangles to the legend elements
    legend.append("rect")
        .attr("width", legendWidth)
        .attr("height", legendWidth)
        .style("fill", colorScale);

    // Add text to the legend elements
    legend.append("text")
        .attr("x", 24)
        .attr("y", 12)
        .text((d) => d);

    // Add text labels to each tile
    if (tileLabels) {
        svg.selectAll()
            .data(processedData)
            .enter()
            .append("text")
            .attr("x", (d) => xScale(d.date) + xScale.bandwidth() / 2) // center text
            .attr("y", (d) => yScale(d.hour) + yScale.bandwidth() / 2) // center text
            .attr("dy", ".35em") // vertically align middle
            .text((d) => d.count)
            .attr("font-size", "8px")
            .attr("fill", "white")
            .attr("text-anchor", "middle")
            .style("pointer-events", "none")
            .style("opacity", "0.75");
    }
    else  // add tooltips to each tile
    {
        svg.selectAll("rect")
            .data(processedData)
            .append("title")
            .text((d) => d.count);
    }

    // Add X-axis
    svg.append("g")
        .attr("transform", `translate(0,${height})`)
        .call(
            d3.axisBottom(xScale).tickValues(
                xScale.domain().filter(function (d, i) {
                    return !(i % 5);
                })
            )
        ); // Adjust the tick interval as needed

    // Add Y-axis
    svg.append("g").call(d3.axisLeft(yScale));

    // Add X-axis label
    svg.append("text")
        .attr("x", width / 2)
        .attr("y", height + 40)
        .attr("text-anchor", "middle")
        .style("font-size", "14px")
        .text("Day of the year");

    // Add Y-axis label
    svg.append("text")
        .attr("x", -(height / 2))
        .attr("y", -40)
        .attr("text-anchor", "middle")
        .attr("transform", "rotate(-90)")
        .style("font-size", "14px")
        .text("Hour of the day");

    // Center the chart in the div
    d3.select("#heatmap")
        .style("display", "flex")
        .style("justify-content", "center")
        .style("align-items", "center");
}

// take date of the form YYYY-MM-DD as one parameter, and the hour of the day as another parameter,
// and return a search string for the beginning of the corresponding common timestamp.
// example: buildSearch('2020-01-01', '12') would return '01/Jan/2020:12:'
function buildTimestampSearch(date, hour) {
    const monthnum = date.substring(5, 7);
    // convert month number to month name
    const monthnames = [
        "Jan",
        "Feb",
        "Mar",
        "Apr",
        "May",
        "Jun",
        "Jul",
        "Aug",
        "Sep",
        "Oct",
        "Nov",
        "Dec",
    ];
    const month = monthnames[monthnum - 1];
    const day = date.substring(8, 10);
    const year = date.substring(0, 4);
    // add leading zero to hour if necessary
    const hourPad = hour.toString().padStart(2, "0");
    const timestamp = day + "/" + month + "/" + year + ":" + hourPad + ":";
    return timestamp;
}

// uiSearch is called when the search button is clicked by user
function uiSearch() {
    const searchInput = document.getElementById("search-input");
    search = searchInput.value;
    console.log("uiSearch: searching for " + search);

    // add search term to URL
    const url = new URL(window.location.href);
    url.searchParams.set("search", search);
    url.searchParams.delete("page");
    window.history.replaceState({}, "", url);

    doSearch();
}

// do search on log
function doSearch() {
    const searchInput = document.getElementById("search-input");
    searchInput.value = search; // set search box to search term
    console.log("doSearch: searching for " + search);

    // abort any pending fetches
    if (fetchCount > 0) {
        console.log("Aborting " + fetchCount + " fetches");
        controller.abort();
    }
    controller = new AbortController();
    fetchCount = 0;

    // remove any page parameter from URL
    const url = new URL(window.location.href);
    url.searchParams.delete("page");
    window.history.replaceState({}, "", url);

    // run search on server
    if (search == "") {
        console.log("ERROR: search is empty!");
    } else {
        let searchURL;
        if (logType == "clf") {
            searchURL = "clftail.php";
        } else {
            searchURL = "authtail.php";
        }
        fetch(searchURL + "?search=" + search + "&n=" + 2500)
            .then((response) => response.text())
            .then((data) => {
                // write the search results to the log div
                const logDiv = document.getElementById("log");
                const pageSpan = document.getElementById("page");
                updateTable(data);
                pageSpan.innerHTML = search;

                // disable all other buttons and
                const buttons = document.querySelectorAll("button");
                buttons.forEach((button) => {
                    button.disabled = true;
                    button.classList.add("disabled");
                });

                // enable search button
                const searchButton = document.getElementById("search-button");
                searchButton.disabled = false;
                searchButton.classList.remove("disabled");

                // add a reset button to the left of the search text box if it doesn't exist
                const resetButton = document.getElementById("reset-button");
                if (resetButton === null) {
                    const resetButton = document.createElement("button");
                    resetButton.id = "reset-button";
                    resetButton.innerHTML = "Reset";
                    resetButton.classList.add("toggle-button");
                    resetButton.onclick = resetSearch;
                    const searchDiv = document.getElementById("search-header");
                    searchDiv.insertBefore(resetButton, searchDiv.firstChild);
                } else {
                    resetButton.disabled = false;
                    resetButton.classList.remove("disabled");
                }

                // report the number of results
                const count = JSON.parse(data).length - 1;  // don't count header row
                console.log("doSearch: " + count + " results");
                const searchStatus = document.getElementById("status");
                searchStatus.innerHTML = "<b>" + count + " items</b>";

                // update the heatmap with the search term
                plotHeatmap(search);
            });
    }
}

// reset search, re-enable all buttons and remove reset button
function resetSearch() {
    const searchInput = document.getElementById("search-input");
    const searchButton = document.getElementById("search-button");
    const resetButton = document.getElementById("reset-button");
    const buttons = document.querySelectorAll("button");
    buttons.forEach((button) => {
        button.disabled = false;
        button.classList.remove("disabled");
    });
    searchButton.innerHTML = "Search";
    searchInput.value = "";
    resetButton.remove();
    pollLog();
    plotHeatmap();
}

// get host names from IP addresses
function getHostNames(ips, signal) {
    console.log("Getting host names for " + ips);
    fetchCount++;
    // Grab each ip address and send to rdns.php
    ips.forEach((ip) => {
        // Check cache first
        if (hostnameCache[ip]) {
            updateHostNames(hostnameCache[ip], ip);
        } else {
            fetch("rdns.php?ip=" + ip, { signal })
                .then((response) => response.text())
                .then((data) => {
                    console.log("rdns: " + data);
                    // Cache the data
                    hostnameCache[ip] = data;
                    updateHostNames(data, ip);
                    fetchCount--;
                })
                .catch((error) => {
                    if (error.name === "AbortError") {
                        console.log("Fetch safely aborted");
                    } else {
                        console.log("Fetch error:", error);
                    }
                });
        }
    });

    function updateHostNames(data, ip) {
        // if data is in the form of an IP address, leave it alone. if it's in the form of a hostname, extract domain.tld
        let hostname;
        let whoisLink;
        if (data.match(/^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$/)) {
            // data is an IP address
            hostname = null;
        } else {
            // data is a hostname, extract only the last two parts (domain.tld)
            const parts = data.split(".");
            hostname = parts[parts.length - 2] + "." + parts[parts.length - 1];
            console.log("domain: " + hostname);
        }
        if (hostname === null) {
            whoisLink = "-";
        } else {
            // Get all cells with id of the form hostname-ipAddress
            const hostnameCells = document.querySelectorAll(
                '[id^="hostname-' + ip + '"]'
            );
            const whoisCall = 'onclick="whois(' + "'" + hostname + "'" + '); return false"';
            whoisLink = '<a class="blue" href="#" ' + whoisCall + '>' + hostname + '</a>';
            hostnameCells.forEach((cell) => {
                cell.innerHTML = whoisLink;
            });
        }
    }
}

// get geolocations and orgs from IP addresses using ip-api.com
function getGeoLocations(ips, signal) {
    console.log("Getting geolocations for " + ips);
    fetchCount++;
    // Grab each ip address and send to ip-api.com
    let waitTime = 0;
    ips.forEach((ip) => {
        // check cache first
        if (geoCache[ip]) {
            updateGeoLocations(geoCache[ip], ip);
        } else {
            setTimeout(
                () => fetchGeoLocation(ip),
                waitTime,
                { signal }
            );
            waitTime += apiWait;
        }
    });

    function updateGeoLocations(data, ip) {
        // update the table cells
        if (geolocate) {
            // Get all cells with id of the form geo-ipAddress
            const geoCells = document.querySelectorAll(
                '[id^="geo-' + ip + '"]'
            );
            // set each cell in geoCells to data
            geoCells.forEach((cell) => {
                cell.innerHTML =
                    data.city + ", " +
                    data.region + ", " +
                    data.countryCode;
            });
        }
        if (orgNames) {
            // Get all cells with id of the form org-ipAddress
            const orgCells = document.querySelectorAll(
                '[id^="org-' + ip + '"]'
            );
            // set each cell in orgCells to data
            orgCells.forEach((cell) => {
                cell.innerHTML = data.org;
            });
        }
    }

    function fetchGeoLocation(ip) {
        fetch("geo.php?ip=" + ip, { signal })
            .then((response) => response.json())
            .then((data) => {
                // cache the data
                geoCache[ip] = data;
                updateGeoLocations(data, ip);
                fetchCount--;
            })
            .catch((error) => {
                if (error.name === "AbortError") {
                    console.log("Fetch safely aborted");
                } else {
                    console.log("Fetch error:", error);
                }
            });
    }
}

// function to setup polling
function runWatch() {
    const uielements = [...document.querySelectorAll("button")];
    const textedit = document.getElementById("search-input");
    uielements.push(textedit);
    const watchButton = document.getElementById("watch-button");
    page = 0; // reset page
    if (polling) {
        // stop polling
        polling = false;
        clearInterval(pollInterval);
        watchButton.innerHTML = "Watch";
        watchButton.classList.remove("red");
        // enable all other ui elements
        uielements.forEach((uielement) => {
            uielement.disabled = false;
            uielement.classList.remove("disabled");
        });
        pollLog();
    } else {
        pollLog();
        polling = true;
        pollInterval = setInterval(pollLog, 10000);
        // disable all other ui elements
        uielements.forEach((uielement) => {
            uielement.disabled = true;
            uielement.classList.add("disabled");
        });
        // enable watch button
        watchButton.disabled = false;
        watchButton.classList.remove("disabled");
        watchButton.innerHTML = "Stop";
        watchButton.classList.add("red");
    }
}

// run whois query on IP address string using the ARIN.net web service. the
// response is a JSON object containing the whois information.
function whois(ip) {
    const whoisDiv = document.getElementById("whois");
    whoisDiv.innerHTML = "<h2>Whois " + ip + "...</h2>";
    fetch("whois.php?ip=" + ip)
        .then((response) => response.text())
        .then((data) => {
            // remove comment lines from whois data
            data = data.replace(/^#.*$/gm, "");

            // remove all blank lines from whois data
            data = data.replace(/^\s*[\r\n]/gm, "");

            // output to whois div
            whoisHTML = "<h2>Whois " + ip + "</h2>";
            whoisHTML += data;
            whoisDiv.innerHTML = whoisHTML;
        });
}
