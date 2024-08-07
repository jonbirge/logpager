// Function to send POST request to blacklist.php with a given IP address in the body of the POST
function blacklistAdd(ip, type, lastTime, log = null) {
    // convert lastTime (a CLF timestamp string) to Date object and then to SQL timestamp
    let lastTimeDate;
    if (lastTime === null || lastTime === "") {
        lastTimeDate = new Date();
    } else {
        lastTimeDate = parseCLFDate(lastTime);
    }
    const lastTimeConv = lastTimeDate.toISOString().slice(0, 19).replace("T", " ");
    // console.log("blacklist: adding " + ip + " as " + type + " at " + lastTimeConv);
    
    // send the IP address to the server
    const formData = new FormData();
    formData.append('ip', ip);
    formData.append('last_seen', lastTimeConv);
    formData.append('log_type', type);
    if (log !== null) {
        formData.append('log', log);
    }
    fetch("addblacklist.php", {
        method: "POST",
        body: formData,
    })
        .then((response) => response.text())
        .then((data) => {
            console.log("addblacklist.php => " + data);
            const blockButtons = document.querySelectorAll(`[id^="block-${ip}"]`);
            blockButtons.forEach((button) => {
                button.outerHTML = makeDisabledButton();
            });
        });
}

// Function to send DELETE request to blocklist.php?ip=IP_ADDRESS
function blacklistRemove(ip, type, lastTime, log) {
    console.log("blacklist: remove " + ip);

    // send the IP address to the server
    fetch("blacklist.php?ip=" + ip, {
        method: "DELETE",
    })
        .then((response) => response.text())
        .then((data) => {
            console.log("blacklist.php => " + data);
            const blockButtons = document.querySelectorAll(`[id^="block-${ip}"]`);
            blockButtons.forEach((button) => {
                button.outerHTML = makeBlacklistButton(ip, type, lastTime, log);
            });
        });
}

// Make blacklist button
function makeBlacklistButton(ip, type = "none", lastTime = "", log = "NULL") {
    const blacklistID = `id="block-${ip}"`;
    const blacklistCall = `onclick="blacklistAdd('${ip}','${type}','${lastTime}','${log}');"`;
    return `<button ${blacklistID} class="toggle-button tight" ${blacklistCall}>block</button>`;
}

// Make unblacklist button
function makeUnblacklistButton(ip, type = "none", lastTime = "", log = "NULL") {
    const blacklistCall =
        `onclick="blacklistRemove('${ip}','${type}','${lastTime}','${log}');"`;
    const blacklistID = `id="block-${ip}"`;
    return `<button ${blacklistID} class="toggle-button tight red" ${blacklistCall}">unblock</button>`;
}

// Make disabled button
function makeDisabledButton() {
    return `<button class="toggle-button tight disabled" disabled>blocked</button>`;
}
