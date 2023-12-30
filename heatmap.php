<?php

// Get an optional 'ip' query string parameter
$searchTerm = $_GET['search'] ?? null;

// IP addresses to exclude from counts
include 'exclude.php';
$excludedIPs = getExcludedIPs();

// Log file to read
$logFilePath = '/access.log';

// Open the log file for reading
$logFile = fopen($logFilePath, 'r');
if (!$logFile) {
    echo "<p>Failed to open log file.</p>";
    return;
}

// Hour integer to string conversion function
function hourStr($hour) {
    if ($hour < 10) {
        return "0$hour";
    } else {
        return "$hour";
    }
}

// Initialize an empty array to store the log summary data
$logSummary = [];

// Read each line of the log file
while (($line = fgets($logFile)) !== false) {
    // Skip this log entry if the search term isn't found in $line
    if ($searchTerm !== null && strpos($line, $searchTerm) === false) {
        continue;
    }

    // Extract the elements from the CLF log entry
    $logEntry = explode(' ', $line);
    $ipAddress = $logEntry[0];

    // Skip this log entry if the IP address is in the excluded list
    if (in_array($ipAddress, $excludedIPs)) {
        continue;
    }

    // Extract the timestamp from the CLF log entry
    $timeStamp = $logEntry[3];

    // Convert the timestamp to a DateTime object
    $date = DateTime::createFromFormat('[d/M/Y:H:i:s', $timeStamp);

    // Check if the DateTime object was created successfully
    if ($date !== false) {
        // Get the date in the format YYYY-MM-DD
        $dayOfYear = $date->format('Y-m-d');
        // Get the hour of the day
        $hour = $date->format('G'); // 24-hour format without leading zeros
    } else {
        echo "<p>Invalid timestamp format encountered: $timeStamp</p>";
        return;
    }

    // Initialize the count for the day of the year and hour of the day
    $hStr = hourStr($hour);
    if (!isset($logSummary[$dayOfYear][$hStr])) {
        $logSummary[$dayOfYear][$hStr] = 0;
    }
    // Increment the count for the day of the year and hour of the day
    $logSummary[$dayOfYear][$hStr]++;
}

// Close the log file
fclose($logFile);

// Echo the log summary data as JSON
echo json_encode($logSummary);

?>