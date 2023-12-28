<?php

// IP addresses to exclude from counts
$excludedIPs = ['173.48.140.140', '172.58.222.184', '167.71.120.76', '159.203.109.206'];

// Log file to read
$logFilePath = '/access.log';

// Open th e log file for reading
$logFile = fopen($logFilePath, 'r');
if (!$logFile) {
    echo "Failed to open log file.";
    return;
}

// Initialize an empty array to store the log summary data
$logSummary = [];

// Read each line of the log file
while (($line = fgets($logFile)) !== false) {
    // Extract the IP from the CLF log entry
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
        // Get the day of the year
        // $dayOfYear = $date->format('z') + 1; // Adding 1 because 'z' starts at 0
        // Get the date in the format YYYY-MM-DD
        $dayOfYear = $date->format('Y-m-d');
        // Get the hour of the day
        $hour = $date->format('G') + 1; // 24-hour format without leading zeros
    } else {
        echo "Invalid timestamp format!";
        return;
    }

    // Increment the count for the day of the year and hour of the day
    if (isset($logSummary[$dayOfYear][$hour])) {
        $logSummary[$dayOfYear][$hour]++;
    } else {
        $logSummary[$dayOfYear][$hour] = 1;
        // echo "Starting new day and hour: ($dayOfYear, $hour) <br>";
    }
}

// Close the log file
fclose($logFile);

// Echo the log summary data as JSON
echo json_encode($logSummary);

?>
