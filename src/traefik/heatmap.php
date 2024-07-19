<?php

// Include traefik.php
include 'traefik.php';

function heatmap($searchDict)
{
    // Concatenate log files
    $tmpFilePath = getTempLogFilePath();

    // Get search parameters
    $search = $searchDict['search'];
    $ip = $searchDict['ip'];
    $dateStr = $searchDict['date'];
    $stat = $searchDict['stat'];

    // Open the log file for reading
    $logFile = fopen($tmpFilePath, 'r');
    if (!$logFile) {
        echo "<p>Failed to open log file.</p>";
        return;
    }

    // Initialize an empty array to store the log summary data
    $logSummary = [];

    // Read each line of the log file
    while (($line = fgets($logFile)) !== false) {
        // Extract the elements from the CLF log entry
        $logEntry = explode(' ', $line);

        // Extract the IP address from the CLF log entry
        $ipAddress = $logEntry[0];

        // Extract the timestamp from the CLF log entry
        $timeStamp = $logEntry[3];

        // Convert the timestamp to a DateTime object
        $date = DateTime::createFromFormat('[d/M/Y:H:i:s', $timeStamp);

        // If $ip is set, check if $ipAddress contains $ip
        if ($ip) {
            if (strpos($ipAddress, $ip) === false) {
                continue;
            }
        }

        // If $dateStr is set, check if $date contains $dateStr
        if ($dateStr) {
            if (strpos($timeStamp, $dateStr) === false) {
                continue;
            }
        }

        // If $stat is set, check if $status matches $stat
        if ($stat) {
            $status = $logEntry[8];
            if ($status !== $stat) {
                continue;
            }
        }

        // If $search is set, check if $line contains $search
        if ($search) {
            if (strpos($line, $search) === false) {
                continue;
            }
        }

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

    // Delete the temporary log file
    unlink($tmpFilePath);

    // Echo the log summary data as JSON
    echo json_encode($logSummary);
}

// Hour integer to string conversion function
function hourStr($hour)
{
    if ($hour < 10) {
        return "0$hour";
    } else {
        return "$hour";
    }
}
