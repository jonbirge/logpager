// drop the timezone from a timestamp string
function dropTimezone(timestamp) {
    return timestamp.replace(/\s.*$/, "");
}

// create a Date object from a log timestamp of the form DD/Mon/YYYY:HH:MM:SS, assuming UTC timezone
function parseCLFDate(clfstamp) {
    clfstamp = dropTimezone(clfstamp);  // remove the timezone (assume UTC)
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
        return seconds + "<br>sec";
    }
}
