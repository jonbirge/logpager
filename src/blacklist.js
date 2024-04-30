// global cache
// TODO: make this a private member of a blacklist controller object
let blackList = [];

// update blacklist cache from server
function loadBlacklist() {
    fetch("blacklist.php")
        .then((response) => response.json())
        .then((data) => {
            if (!Array.isArray(data)) {
                throw new Error('blacklist.php did not return an array');
            }
            blackList.push(...data);
        });
}

// Function to send POST request to blacklist.php with a given IP address in the body of the POST
function blacklistAdd(ip, type, lastTime, log) {
    // convert lastTime (a CLF timestamp string) to Date object and then to SQL timestamp
    let lastTimeDate;
    if (lastTime === null || lastTime === "") {
        lastTimeDate = new Date();
    } else {
        lastTimeDate = parseCLFDate(lastTime);
    }
    const lastTimeConv = lastTimeDate.toISOString().slice(0, 19).replace("T", " ");
    console.log("blacklist: add " + ip + " as " + type + " at " + lastTimeConv);
    
    // update global blacklist cache manually
    blackList.push(ip);
    
    // send the IP address to the server
    const formData = new FormData();
    formData.append('ip', ip);
    formData.append('last_seen', lastTimeConv);
    formData.append('log_type', type);
    if (log !== null) {
        formData.append('log', log);
    }
    fetch("blacklist.php", {
        method: "POST",
        body: formData,
    })
        .then((response) => response.text())
        .then((data) => {
            // console.log("blacklist: " + data);
            const blockButtons = document.querySelectorAll(`[id^="block-${ip}"]`);
            blockButtons.forEach((button) => {
                button.outerHTML = makeBlacklistButton(ip, type, lastTime, log);
            });
        });
}

// Function to send DELETE request to blocklist.php?ip=IP_ADDRESS
function blacklistRemove(ip, type, lastTime, log) {
    // log it
    console.log("blacklist: remove " + ip);

    // update blacklist cache manually
    blackList = blackList.filter((item) => item !== ip);

    // send the IP address to the server
    fetch("blacklist.php?ip=" + ip, {
        method: "DELETE",
    })
        .then((response) => response.text())
        .then((data) => {
            const blockButtons = document.querySelectorAll(`[id^="block-${ip}"]`);
            blockButtons.forEach((button) => {
                button.outerHTML = makeBlacklistButton(ip, type, lastTime, log);
            });
        });
}

// Make blacklist button
function makeBlacklistButton(ip, type = "none", lastTime = "", log = "N/A") {
    if (blackList.includes(ip)) {  // already blacklisted it
        const blacklistCall =
            `onclick="blacklistRemove('${ip}','${type}','${lastTime}','${log}');"`;
        const blacklistID = `id="block-${ip}"`;
        return `<button ${blacklistID} class="toggle-button tight red" ${blacklistCall}">unblock</button>`;
    } else {  // not blacklisted yet
        const blacklistCall =
            `onclick="blacklistAdd('${ip}','${type}','${lastTime}','${log}');"`;
        const blacklistID = `id="block-${ip}"`;
        return `<button ${blacklistID} class="toggle-button tight" ${blacklistCall}>block</button>`;
    }
}
