// hard-wired settings
const geolocate = true; // pull IP geolocation from external service?
const tileLabels = false; // show tile labels on heatmap?
const fillToNow = true; // fill heatmap to current time?
const heatmapRatio = 0.5; // width to height ratio of heatmap
const maxDetailLength = 48; // truncation length of log details
const maxGeoRequests = 30; // maximum number of IPs to externally geolocate at once
const pollWait = 30; // seconds to wait between polling the server
const mapWait = 15; // minutes to wait between updating the heatmap (always)

// global variables
let params = new URLSearchParams(window.location.search);
let polling = false;
let pollInterval;
let heatmapTimeout;
let controller;
let page = params.get("page") !== null ? Number(params.get("page")) : 0;
let search = params.get("search");
let summary = params.get("summary") !== null ? params.get("summary") == "true" : true; // applies to search
let logType = params.get("type") !== null ? params.get("type") : "auth"; // "clf" or "auth"
let tableLength = 0; // used to decide when to reuse the table
let logLines = []; // cache of current displayed table data
let geoCache = {}; // cache of geolocation data

// start initial data fetches
loadManifest();
loadBlacklist();

// create update interval
updateClock();
setInterval(updateClock, 1000);

// decide what to do on page load
if (search !== null) {
    // search trumps page
    console.log("page load: searching for " + search + ", summary = " + summary);
    window.onload = doSearch(search, summary);
} else {
    console.log("page load: loading " + logType + " log...");
    // on window load run pollServer() and plotHeatmap()
    window.onload = () => {
        pollLog();
        plotHeatmap();
    };
}

// enable the search button when something is typed in the search box
document.getElementById("search-input").oninput = function () {
    const searchButton = document.getElementById("search-button");
    // if the search box is empty, disable the search button
    if (this.value === "") {
        searchButton.disabled = true;
        searchButton.classList.add("disabled");
    } else {
        searchButton.disabled = false;
        searchButton.classList.remove("disabled");
    }
};

// load the log manifest and update the log type tabs
function loadManifest() {
    fetch("manifest.php")
        .then((response) => response.json())
        .then((data) => {
            const haveCLF = data.includes("access.log");
            const haveAuth = data.includes("auth.log");
            if (!haveCLF) {
                document.getElementById("clftab").style.display = "none";
            } else {
                document.getElementById("clftab").style.display = "";
            }
            if (!haveAuth) {
                document.getElementById("authtab").style.display = "none";
                logType = "clf"; // because auth is default
            } else {
                document.getElementById("authtab").style.display = "";
            }
            // highlight the current log type
            if (logType == "clf") {
                document.getElementById("clftab").classList.add("selected");
                document.getElementById("authtab").classList.remove("selected");
            } else {
                document.getElementById("authtab").classList.add("selected");
                document.getElementById("clftab").classList.remove("selected");
            }
        });
}

// ***** function definitions *****

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

// pull the relevent log data from the server
function pollLog() {
    console.log("***** pollLog: fetching page " + page + " of type " + logType);

    // abort any pending fetches
    if (controller) {
        controller.abort();
    }
    controller = new AbortController();
    if (page < 0) {
        page = 0; // reset page
    }

    // clear URL search and summary parameters
    const url = new URL(window.location.href);
    url.searchParams.delete("search");
    url.searchParams.delete("summary");
    url.searchParams.set("type", logType);
    search = null;

    // update page to show loading...
    const statusDiv = document.getElementById("status");
    statusDiv.innerHTML = "<b>Loading...</b>";

    // get the log from the server
    fetch("logtail.php?type=" + logType + "&page=" + page)
        .then((response) => response.text())
        .then((data) => {
            updateTable(data);
        });
}

// search the log and return table of results
function searchLog(searchTerm, doSummary) {
    console.log("searchLog: searching for " + searchTerm + ", summary = " + doSummary);

    // abort any pending fetches
    if (controller) {
        controller.abort();
    }
    controller = new AbortController();

    // disable all other buttons and...
    const buttonDiv = document.getElementById("buttons");
    const buttons = Array.from(buttonDiv.getElementsByTagName("button"));
    buttons.forEach((button) => {
        button.disabled = true;
        button.classList.add("disabled");
    });

    // ...enable search button
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

    // run the search on the server
    let summaryKey = doSummary ? "true" : "false";
    fetch(
        "logsearch.php?type=" +
            logType +
            "&search=" +
            searchTerm +
            "&summary=" +
            summaryKey
    )
        .then((response) => response.text())
        .then((data) => {
            // write the search results to the log div
            let dataLength;
            if (doSummary == true) {
                dataLength = JSON.parse(data).length - 1; // don't count header row
                console.log("searchLog: summary table");
                updateSummaryTable(data);
            } else {
                dataLength = JSON.parse(data).lineCount;
                console.log("searchLog: full table");
                updateTable(data);
            }
            console.log("search: " + dataLength + " results");
        });
}

// sort table data in logLines by column
function sortTable(column, isDate = false) {
    // extract the given column from logLines, minus the header element
    const columnData = logLines.slice(1).map((row) => row[column]);

    // sort the column data, returning the indices of the sorted data
    const indices = columnData.map((_, i) => i);
    if (isDate) {
        indices.sort((a, b) => {
            if (parseCLFDate(columnData[a]) < parseCLFDate(columnData[b])) {
                return 1;
            } else if (
                parseCLFDate(columnData[a]) > parseCLFDate(columnData[b])
            ) {
                return -1;
            }
            return 0;
        });
    } else {
        indices.sort((a, b) => {
            if (columnData[a] < columnData[b]) {
                return 1;
            } else if (columnData[a] > columnData[b]) {
                return -1;
            }
            return 0;
        });
    }

    // apply the sorted indices to logLines
    const sortedLogLines = [logLines[0]];
    indices.forEach((i) => {
        sortedLogLines.push(logLines[i + 1]);
    });

    // write the sorted data to the log div
    logLines = sortedLogLines;
    refreshTable();
}

// update table HTML from log array
function refreshTable() {
    const logDiv = document.getElementById("log");
    const signal = controller.signal;
    const dataLength = logLines.length;

    // check to see if the table needs to be rebuilt
    if (dataLength != tableLength) {
        console.log("updateTable: rebuilding table");
        tableLength = logLines.length;
        let table0 = '<table id="log-table" class="log">';
        for (let i = 0; i < logLines.length; i++) {
            table0 += '<tr id="row-' + i + '"></tr>';
        }
        table0 += "</table>";
        logDiv.innerHTML = table0;
    }

    // utility function to create HTML link for sorting
    function addSortLink(idx, name, isDate = false) {
        return (
            '<a class="header" href="#" onclick="sortTable(' +
            idx +
            ", " +
            isDate +
            '); return false;">' +
            name +
            "</a>"
        );
    }

    // write table headers from first row
    const headers = logLines[0];
    const headrow = document.getElementById("row-0");
    let ageIndex = null;
    let detailIndex = null;
    let row = "";
    for (let j = 0; j < headers.length; j++) {
        const headerName = headers[j];
        switch (headerName) {
            case "IP":
                row += "<th>" + addSortLink(j, headerName) + "</th>";
                if (geolocate) {
                    row += '<th class="hideable">Domain</th>';
                    row += '<th class="hideable">Organization</th>';
                    row += "<th>Geolocation</th>";
                }
                break;
            case "Details":
                detailIndex = j;
                row += '<th class="hideable">' + headerName + "</th>";
                break;
            case "Age":
                ageIndex = j;
                row += "<th>" + addSortLink(j, headerName, true) + "</th>";
                break;
            default:
                row += "<th>" + addSortLink(j, headerName) + "</th>";
        }
    }
    headrow.innerHTML = row;

    // write table rows from remaining rows
    let ips = [];
    for (let i = 1; i < dataLength; i++) {
        // iterate over rows
        const rowElement = document.getElementById("row-" + i);
        const rawTimestamp = logLines[i][ageIndex];
        const clfStamp = dropTimezone(rawTimestamp);
        const dateStamp = parseCLFDate(rawTimestamp); // assume UTC
        let logDetails;
        if (detailIndex !== null) {
            logDetails = logLines[i][detailIndex];
        } else {
            logDetails = "N/A";
        }
        row = "";
        for (let j = 0; j < logLines[i].length; j++) {
            // build row
            const headerName = headers[j];
            switch (headerName) {
                case "IP":
                    const ip = logLines[i][j];
                    ips.push(ip);
                    const srchlink = `?type=${logType}&summary=false&search=ip:${ip}`;
                    row += `<td><a href=${srchlink}>${ip}</a><br><nobr>`;
                    row += makeBlacklistButton(
                        ip,
                        logType,
                        clfStamp,
                        logDetails
                    );
                    const intelLink = `onclick="window.open('intel.php?ip=${ip}'); return false"`;
                    row += ` <button id="intel-${ip}" class="toggle-button tight" ${intelLink}>intel</button></nobr></td>`;
                    if (geolocate) {
                        const hostnameid = `hostname-${ip}`;
                        row += `<td class="hideable" id="${hostnameid}"></td>`;
                        const orgid = `org-${ip}`;
                        row += `<td class="hideable" id="${orgid}"></td>`;
                        const geoid = `geo-${ip}`;
                        row += `<td id="${geoid}"></td>`;
                    }
                    break;
                case "Age":
                    const timediff = timeDiff(dateStamp, new Date());
                    const jsonDate = dateStamp.toJSON();
                    row += `<td id=timestamp:${jsonDate}>${timediff}</td>`;
                    break;
                case "Details":
                    const rawRequest = logLines[i][j];
                    const truncRequest =
                        rawRequest.length > maxDetailLength
                            ? `${rawRequest.substring(0, maxDetailLength)}...`
                            : rawRequest;
                    row += `<td class="code hideable">${truncRequest}</td>`;
                    break;
                case "Status":
                    const greenStatus = ["200", "304", "OK"];
                    const redStatus = [
                        "308",
                        "400",
                        "401",
                        "403",
                        "404",
                        "500",
                        "FAIL",
                    ];
                    const status = logLines[i][j];
                    if (greenStatus.includes(status)) {
                        row += `<td class="green">${status}</td>`;
                    } else if (redStatus.includes(status)) {
                        row += `<td class="red">${status}</td>`;
                    } else {
                        row += `<td class="gray">${status}</td>`;
                    }
                    break;
                default:
                    row += `<td>${logLines[i][j]}</td>`;
            }
        }
        rowElement.innerHTML = row;
    }

    // asyncronously get the host locations from the IPs
    const ipSet = [...new Set(ips)]; // Get unique IP addresses
    if (geolocate) getGeoLocations(ipSet, signal);
}

// function to enable or disable a set of buttons
function toggleButtons(buttons, enable) {
    if (polling) {
        buttons.forEach((button) => {
            button.disabled = true;
            button.classList.add("disabled");
        });
    } else {
        buttons.forEach((button) => {
            button.disabled = !enable;
            if (enable) {
                button.classList.remove("disabled");
            } else {
                button.classList.add("disabled");
            }
        });
    }
}

// take JSON array of common log data and write HTML table
// TODO: consolidate all table updating into this function by having all table
// data returned with both metadata (as in here) and the actual log table data,
// adding a new metadata field to denote if the table data is search data.
function updateTable(jsonData) {
    const logdata = JSON.parse(jsonData);
    const pageCount = parseInt(logdata.pageCount, 10);
    const lineCount = parseInt(logdata.lineCount, 10);
    const page = parseInt(logdata.page, 10);
    logLines = logdata.logLines;

    // report the number of results in the status div
    const searchStatus = document.getElementById("status");
    if (logdata.search !== undefined) {
        searchStatus.innerHTML =
            "<b>Found " + lineCount + " matching log entries</b>";
    } else {
        searchStatus.innerHTML = "<b>Paging " + lineCount + " log entries</b>";
    }

    // write HTML from data...
    refreshTable();

    // handle case where we're at the last page
    const nextButtons = document.querySelectorAll('[id^="next-"]');
    toggleButtons(nextButtons, !(page >= pageCount));

    // update the page number and URL
    const url = new URL(window.location.href);
    const pageSpan = document.getElementById("page");
    const prevButtons = document.querySelectorAll('[id^="prev-"]');
    if (page == 0) {
        toggleButtons(prevButtons, false);
        if (pageCount == 0) {
            // everything fits on one page
            pageSpan.innerHTML = "All results";
        } else {
            // multiple pages
            pageSpan.innerHTML = "Latest page of " + pageCount;
            url.searchParams.set("page", 0);
        }
    } else {
        toggleButtons(prevButtons, true);
        pageSpan.innerHTML = "Page " + page + " of " + pageCount;
        url.searchParams.set("page", page);
    }
    window.history.replaceState({}, "", url);
}

// take JSON array of search log data and write HTML table
function updateSummaryTable(jsonData) {
    logLines = JSON.parse(jsonData);

    // set dataLength to the minimum of data.length and maxLogLength
    const dataLength = logLines.length;

    // go through the data and add up all the counts
    let total = 0;
    for (let i = 1; i < logLines.length; i++) {
        total += parseInt(logLines[i][0]);
    }

    // report the number of results in the status div
    const searchStatus = document.getElementById("status");
    searchStatus.innerHTML =
        "<b>Found " +
        (dataLength - 1) +
        " IP addresses from " +
        total +
        " matching log entries</b>";

    // write HTML from data...
    refreshTable();

    // update the page number
    const pageSpan = document.getElementById("page");
    pageSpan.innerHTML = "Summary search results";
}

// plot heatmap of log entries by hour and day, potentially including a search term
function plotHeatmap(searchTerm, plotLogType = null) {
    // cancel existing heatmap timeout
    if (heatmapTimeout) {
        clearTimeout(heatmapTimeout);
    }

    // set plotLogType to global logType if not provided
    if (plotLogType === null) {
        plotLogType = logType;
    }

    // Build data query URL
    let heatmapURL = "heatmap.php?type=" + plotLogType;
    if (searchTerm) {
        heatmapURL += "&search=" + searchTerm;
    }

    // get summary data from server
    console.log("plotHeatmap: fetching " + heatmapURL);
    fetch(heatmapURL)
        .then((response) => response.json())
        .then(buildHeatmap);

    // set interval to update the heatmap every mapWait minutes
    console.log("plotHeatmap: refresh time " + mapWait + " minutes");
    heatmapTimeout = setTimeout(() => {
        plotHeatmap(searchTerm, plotLogType);
    }, mapWait * 60 * 1000);
}

// Take JSON array of command log data and build SVG heatmap
function buildHeatmap(jsonData) {
    // Check if SVG element already exists and remove if so
    const svgElement = document.querySelector("svg");
    if (svgElement) {
        svgElement.remove();
    }

    // Iterate through every entry in jsonDate[date][hour] and create an array of Date objects
    let dateObjs = [];
    Object.entries(jsonData).forEach(([date, hours]) => {
        Object.keys(hours).forEach((hour) => {
            dateObjs.push(new Date(date + "T" + hour + ":00:00Z"));
        });
    });

    // Add current time to dateObjs if fillToNow is true
    if (fillToNow) {
        dateObjs.push(new Date());
    }

    // Sort dateObjs from earliest to latest
    dateObjs.sort((a, b) => a - b);

    // Get the earliest and latest dates
    const earliestDate = dateObjs[0];
    const latestDate = dateObjs[dateObjs.length - 1];

    // Create an array of Date objects for every hour between earliestDate and latestDate
    let processedData = [];
    for (
        let thedate = new Date(earliestDate);
        thedate < latestDate;
        thedate.setHours(thedate.getHours() + 1)
    ) {
        const dayStr = thedate.toISOString().slice(0, 10);
        const hourStr = thedate.toISOString().slice(11, 13);
        let count;
        if (jsonData[dayStr] && jsonData[dayStr][hourStr]) {
            count = jsonData[dayStr][hourStr];
        } else {
            count = 0;
        }
        processedData.push({
            date: dayStr,
            hour: hourStr,
            count: count,
        });
    }

    // Get all unique dates from processedData
    const allDates = [...new Set(processedData.map((d) => d.date))];

    // Set dimensions for the heatmap
    const cellSize = 10; // size of each tile
    const ratio = heatmapRatio; // width to height ratio
    const margin = { top: 0, right: 50, bottom: 50, left: 50 };
    const width = ratio * allDates.length * cellSize;
    const height = 24 * cellSize; // 24 hours

    // Creating scales for date axes
    const xScale = d3.scaleBand().domain(allDates).range([0, width]);

    // Create array of hour label strings with leading zeros
    const hours = [];
    for (let i = 0; i < 24; i++) {
        hours.push(i.toString().padStart(2, "0"));
    }

    // Create d3 scale for hour axis as string categories from hours array
    const yScale = d3.scaleBand().domain(hours).range([0, height]);

    // Create SVG element
    const svg = d3
        .select("#heatmap")
        .append("svg")
        .attr("width", "100%") // Set width to 100%
        .attr(
            "viewBox",
            `${-margin.left} 0 ${width + margin.right + margin.left + 25}
            ${height + margin.bottom + margin.top}`
        ) // viewBox
        .style("max-height", height + "px") // Set height using CSS
        .style("font-family", "DM Sans, sans-serif")
        .append("g")
        .attr("transform", `translate(0,${margin.top})`);

    // Create color scale
    const colorScale = d3
        .scaleLog()
        .base(2)
        .interpolate(() => d3.interpolatePlasma)
        .domain([1, d3.max(processedData, (d) => d.count)])
        .range([0.1, 1]);

    // Create the tiles and make interactive
    let tiles = svg
        .selectAll()
        .data(processedData)
        .enter()
        .append("rect")
        .attr("x", (d) => xScale(d.date))
        .attr("y", (d) => yScale(d.hour))
        .attr("width", xScale.bandwidth() + 1) // create a gap between tiles
        .attr("height", yScale.bandwidth() + 1) // create a gap between tiles
        .style("fill", (d) => colorScale(d.count));

    // Interactivity
    tiles.on("click", function (d) {
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
        handleSearchForm();
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
    legend
        .append("rect")
        .attr("width", legendWidth)
        .attr("height", legendWidth)
        .style("fill", colorScale);

    // Add text to the legend elements
    legend
        .append("text")
        .attr("x", 24)
        .attr("y", 12)
        .text((d) => d)
        .style("font-size", "12px");

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
    } // add tooltips to each tile
    else {
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
                    return !(i % 14);
                })
            )
        )
        .selectAll("text")
        .style("font-family", "DM Sans, sans-serif")
        .style("font-size", "12px");

    // Add Y-axis
    svg.append("g")
        .call(
            d3.axisLeft(yScale).tickValues(
                yScale.domain().filter(function (d, i) {
                    return !(i % 2);
                })
            )
        )
        .selectAll("text")
        .style("font-family", "DM Sans, sans-serif")
        .style("font-size", "12px");

    // Add X-axis label
    svg.append("text")
        .attr("x", width / 2)
        .attr("y", height + 40)
        .attr("text-anchor", "middle")
        .style("font-size", "16px")
        .style("font-weight", 500)
        .text("Day of year");

    // Add Y-axis label
    svg.append("text")
        .attr("x", -(height / 2))
        .attr("y", -40)
        .attr("text-anchor", "middle")
        .attr("transform", "rotate(-90)")
        .style("font-size", "16px")
        .style("font-weight", 500)
        .text("Hour of day");

    // Add title by writing to the "heatmap-title" element
    const titleHTMLElement = document.getElementById("heatmap-title");
    let titleText;
    if (search) {
        titleText = "Search results by time";
    } else {
        titleText = "Log entries by time";
    }
    titleHTMLElement.innerHTML = titleText;

    // Center the chart in the div
    d3.select("#heatmap")
        .style("display", "flex")
        .style("justify-content", "center")
        .style("align-items", "center");
}

// uiSearch is called when the search button is clicked by user
function handleSearchForm() {
    const searchInput = document.getElementById("search-input");
    let searchStr = searchInput.value;
    console.log("handleSearchButton: searching for " + searchStr);

    // add search term to URL
    const url = new URL(window.location.href);
    url.searchParams.set("search", searchStr);
    url.searchParams.delete("page");
    url.searchParams.delete("summary");
    window.history.replaceState({}, "", url);

    doSearch(searchStr, true);
}

// execute search
function doSearch(searchTerm, doSummary) {
    const searchInput = document.getElementById("search-input");
    searchInput.value = searchTerm; // set search box to search term
    console.log("doSearch: searching for " + searchTerm);

    // abort any pending fetches
    if (controller) {
        controller.abort();
    }
    controller = new AbortController();

    // remove any page parameter from URL
    const url = new URL(window.location.href);
    url.searchParams.delete("page");
    window.history.replaceState({}, "", url);

    // clear status divs
    const searchStatus = document.getElementById("status");
    searchStatus.innerHTML = "";

    // run search on server
    if (search == "") {
        console.log("ERROR: search is empty!");
    } else {
        searchLog(searchTerm, doSummary);
        plotHeatmap(searchTerm);
    }
}

// reset search, re-enable all buttons and remove reset button
function resetSearch() {
    const searchInput = document.getElementById("search-input");
    const searchButton = document.getElementById("search-button");
    const resetButton = document.getElementById("reset-button");

    // enable all other buttons and...
    const buttonDiv = document.getElementById("buttons");
    const buttons = Array.from(buttonDiv.getElementsByTagName("button"));
    buttons.forEach((button) => {
        button.disabled = false;
        button.classList.remove("disabled");
    });

    // remove search term from URL
    const url = new URL(window.location.href);
    url.searchParams.delete("search");
    url.searchParams.delete("summary");
    window.history.replaceState({}, "", url);

    // disable search button
    searchButton.disabled = true;
    searchButton.classList.add("disabled");

    // clear search box and remove reset button
    search = null;
    summary = null;
    searchInput.value = "";
    resetButton.remove();

    // load the log
    pollLog();
    plotHeatmap();
}

// get geolocations and update table cells
function getGeoLocations(ips, signal) {
    // take care of everything locally cached
    localHits = 0;
    ips.forEach((ip) => {
        if (geoCache[ip]) {
            localHits += 1;
            updateGeoLocation(geoCache[ip], ip);
            // remove ip from ips
            ips = ips.filter((value) => value !== ip);
        }
    });
    if (localHits > 0) {
        console.log("got " + localHits + " hit(s) from local cache");
    }

    // send remaining ips to remote sql cache, and of those that aren't satisfied, send to external web service
    if (ips.length > 0) {
        ipsJSON = JSON.stringify(ips);
        fetch("geo.php", {
            method: "POST",
            body: ipsJSON,
            signal,
        })
            .then((response) => response.json())
            .then((geodata) => {
                if (geodata === null) {
                    console.log("geo: bad data from server");
                } else {
                    cachedips = Object.keys(geodata);
                    console.log(
                        "got " + cachedips.length + " hit(s) from server cache"
                    );
                    for (let ip of cachedips) {
                        updateGeoLocation(geodata[ip], ip);
                        ips = ips.filter((value) => value !== ip);
                        geoCache[ip] = geodata[ip];
                    }
                }
                // asyncronously recurse queries to external web service for remaining ips
                if (ips.length > 0) {
                    setTimeout(() => recurseFetchGeoLocations(ips), 0);
                }
            })
            .catch((error) => {
                console.log("geo fetch error:", error);
            });
    }

    // function to update geo location for given ip in all matching cells
    function updateGeoLocation(data, ip) {
        // Get all cells with id of the form geo-ipAddress
        const geoCells = document.querySelectorAll('[id^="geo-' + ip + '"]');
        const hostnameCells = document.querySelectorAll(
            '[id^="hostname-' + ip + '"]'
        );
        const orgCells = document.querySelectorAll('[id^="org-' + ip + '"]');
        if (data.status !== undefined) {
            if (data.status != "fail") {
                // set each cell in geoCells to data
                geoCells.forEach((cell) => {
                    cell.innerHTML =
                        data.city +
                        ", " +
                        data.region +
                        ", " +
                        data.countryCode;
                });
                // get rDNS and set hostname
                let hostname;
                if (data.reverse === "" || data.reverse === undefined) {
                    hostname = "-";
                } else {
                    // extract domain.tld from reverse DNS entry
                    const parts = data.reverse.split(".");
                    hostname =
                        parts[parts.length - 2] + "." + parts[parts.length - 1];
                }
                // set each cell in hostnameCells to hostname
                hostnameCells.forEach((cell) => {
                    cell.innerHTML = hostname;
                });
                // set each cell in orgCells to org
                let orgname = "-";
                if (data.org !== undefined && data.org !== "") {
                    orgname = data.org;
                } else if (data.isp !== undefined && data.isp !== "") {
                    orgname = data.isp;
                } else if (data.as !== undefined && data.as !== "") {
                    orgname = data.as;
                }
                orgCells.forEach((cell) => {
                    cell.innerHTML = orgname;
                });
            } else {
                // we have a private address
                geoCells.forEach((cell) => {
                    cell.innerHTML = "local";
                });
                orgCells.forEach((cell) => {
                    cell.innerHTML = "local";
                });
                hostnameCells.forEach((cell) => {
                    cell.innerHTML = "local";
                });
            }
        } else {
            // we got bad JSON
            console.log("* geo: bad data for " + ip + ":");
            console.log(JSON.stringify(data));

            // remove ip from local cache, if it's there
            delete geoCache[ip];

            // write N/A values everywhere
            geoCells.forEach((cell) => {
                cell.innerHTML = "N/A";
            });
            orgCells.forEach((cell) => {
                cell.innerHTML = "N/A";
            });
            hostnameCells.forEach((cell) => {
                cell.innerHTML = "-";
            });
        }
    }

    function recurseFetchGeoLocations(ips, apiCount = 0) {
        // pop the first ip off of ips
        const ip = ips.shift();
        console.log("fetching geo from web service: " + ip);
        fetch("geo.php?ip=" + ip, { signal })
            .then((response) => response.json())
            .then((geodata) => {
                // cache the data
                geoCache[ip] = geodata;
                updateGeoLocation(geodata, ip);
                apiCount++;
                if (apiCount >= maxGeoRequests) {
                    console.log("geo api limit reached!");
                }
                if (ips.length > 0 && apiCount < maxGeoRequests) {
                    recurseFetchGeoLocations(ips, apiCount);
                }
            })
            .catch((error) => {
                if (error.name === "AbortError") {
                    console.log("geo fetch aborted for " + ip);
                } else {
                    console.log("fetch error:", ip, error);
                    updateGeoLocation(null, ip);
                }
            });
    }
}

// function to start and stop log table polling
function runWatch() {
    let uiElements = [...document.querySelectorAll("button")];
    const tableButtons = document.querySelectorAll('[id^="block-"], [id^="intel-"]');
    const textedit = document.getElementById("search-input");
    const watchButton = document.getElementById("watch-button");
    
    // remove any nodes from uiElements that are in tableButtons
    tableButtons.forEach((button) => {
        uiElements = uiElements.filter((element) => element !== button);
    });

    // add search box to uiButtons
    uiElements.push(textedit);

    page = 0; // reset page
    if (polling) {
        // stop polling
        polling = false;
        clearInterval(pollInterval);
        watchButton.innerHTML = "Watch";
        watchButton.classList.remove("red");
        // enable all other ui elements
        uiElements.forEach((uielement) => {
            uielement.disabled = false;
            uielement.classList.remove("disabled");
        });
        pollLog();
    } else {
        pollLog();
        polling = true;
        pollInterval = setInterval(pollLog, 1000 * pollWait);
        // disable all other ui elements
        uiElements.forEach((uielement) => {
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
