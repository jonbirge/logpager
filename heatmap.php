<?php

// Log file to read
$logFilePath = '/access.log';

// Open the log file for reading
$logFile = fopen($logFilePath, 'r');
if (!$logFile) {
    echo "Failed to open log file.";
    return;
}

// Initialize an empty array to store the log summary data
$logSummary = [];

// Read each line of the log file
while (($line = fgets($logFile)) !== false) {
    // Extract the timestamp the CLF log entry
    $logEntry = explode(' ', $line);
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
