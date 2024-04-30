// global cache
let blackList = [];

// TODO: pull everything into this...
// IP blacklist utility class
class IPChecker {
    constructor(list) {
        this.ipSet = new Set(); // Stores individual IPs for quick lookup
        this.cidrBlocks = []; // Stores CIDR blocks with precomputed masks and bases

        // Initialize by iterating over each entry in the list
        list.forEach(item => {
            if (this.isCIDR(item)) {
                const [range, bits] = item.split('/');
                const mask = -1 << (32 - parseInt(bits, 10)) >>> 0;
                const base = this.ipToInt(range) & mask;
                this.cidrBlocks.push({ base, mask });
            } else {
                // Directly add standalone IP addresses to a set for quick access
                this.ipSet.add(item);
            }
        });
    }

    // Converts a string IP address to an integer for easier manipulation and comparison
    ipToInt(ip) {
        return ip.split('.').reduce((acc, octet) => (acc << 8) + parseInt(octet, 10), 0) >>> 0;
    }

    // Utility function to check if an IP is a CIDR or not
    isCIDR(ip) {
        return ip.includes('/');
    }

    // Function to check if an IP is directly in the list
    isInIPList(ip) {
        // Check if the IP is in the set of direct IPs
        return this.ipSet.has(ip);
    }

    // Function to check if an IP is in any CIDR block
    isInCIDR(ip) {
        // Iterate over CIDR blocks to check if the IP is contained within them
        for (const cidr of this.cidrBlocks) {
            if (this.containsIP(cidr, ip)) return true;
        }
        return false;
    }

    // Function to check if an IP is either in the list or in a CIDR block
    isAnywhere(ip) {
        return this.isInIPList(ip) || this.isInCIDR(ip);

    }
    // Helper function to check if a given IP matches a precomputed CIDR block
    containsIP(cidr, ip) {
        const ipNum = this.ipToInt(ip);
        return cidr.base === (ipNum & cidr.mask);
    }
}

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
    console.log("blacklist: remove " + ip);

    // update blacklist cache manually
    blackList = blackList.filter((item) => item !== ip);

    // send the IP address to the server
    fetch("blacklist.php?ip=" + ip, {
        method: "DELETE",
    })
        .then((response) => response.text())
        .then((data) => {
            // consolue.log("blacklist: " + data);
            const blockButtons = document.querySelectorAll(`[id^="block-${ip}"]`);
            blockButtons.forEach((button) => {
                button.outerHTML = makeBlacklistButton(ip, type, lastTime, log);
            });
        });
}

// Make blacklist button
function makeBlacklistButton(ip, type = "none", lastTime = "", log = "N/A") {
    const checker = new IPChecker(blackList);
    if (checker.isInCIDR(ip) && !checker.isCIDR(ip)) {  // regular ip covered by cidr block
        return `<button class="toggle-button tight disabled" disabled>cidr</button>`;
    } else if (checker.isAnywhere(ip)) {  // already blacklisted it
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
