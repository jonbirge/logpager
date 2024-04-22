// update blacklist cache from server
function loadBlacklist(blackListObject) {
    fetch("blacklist.php")
        .then((response) => response.json())
        .then((data) => {
            if (!Array.isArray(data)) {
                throw new Error('blacklist.php did not return an array');
            }
            blackListObject.push(...data);
        });
}

// Function to send POST request to blacklist.php with a given IP address in the body of the POST
function blacklistAdd(ip, lastTime, log = null) {
    // convert lastTime (a CLF timestamp string) to Date object and then to SQL timestamp
    const lastTimeDate = parseCLFDate(lastTime);
    const lastTimeConv = lastTimeDate.toISOString().slice(0, 19).replace("T", " ");
    console.log("blacklist: add " + ip + " as " + logType + " at " + lastTimeConv);
    // update blacklist cache manually
    blackList.push(ip);
    // send the IP address to the server
    const formData = new FormData();
    formData.append('ip', ip);
    formData.append('last_seen', lastTimeConv);
    formData.append('log_type', logType);
    if (log) formData.append('log', log);
    fetch("blacklist.php", {
        method: "POST",
        body: formData,
    })
        .then((response) => response.text())
        .then((data) => {
            const blockButtons = document.querySelectorAll('[id^="block-' + ip + '"]');
            blockButtons.forEach((button) => {
                button.innerHTML = "unblock";
                button.setAttribute("onclick", 'blacklistRemove(' + "'" + ip + "'" + ');');
                button.classList.add("red");
            });
        });
}

// Function to send DELETE request to blocklist.php?ip=IP_ADDRESS
function blacklistRemove(ip) {
    console.log("blacklist: remove " + ip);
    // update blacklist cache manually
    blackList = blackList.filter((item) => item !== ip);
    fetch("blacklist.php?ip=" + ip, {
        method: "DELETE",
    })
        .then((response) => response.text())
        .then((data) => {
            const blockButtons = document.querySelectorAll('[id^="block-' + ip + '"]');
            blockButtons.forEach((button) => {
                button.innerHTML = "block";
                button.setAttribute("onclick", 'blacklistAdd(' + "'" + ip + "'" + ');');
                button.classList.remove("red");
            });
        });
}