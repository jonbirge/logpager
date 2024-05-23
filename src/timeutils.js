// drop the timezone from a timestamp string
function dropTimezone(timestamp) {
    return timestamp.replace(/\s.*$/, "");
}

// create a Date object from a standard log timestamp of the form DD/Mon/YYYY:HH:MM:SS, assuming UTC timezone
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
    const seconds = Math.ceil(diff / 1000) + 1;
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
