<?php

// Include the authparse.php file
include 'authparse.php';

function heatmap($searchDict)
{
    // set $doSearch to false if $searchDict is empty
    $doSearch = !empty($searchDict);

    // Log files to read
    $logFilePaths = array_reverse(getAuthLogFiles());

    // Get search parameters
    $search = $searchDict['search'];
    $ip = $searchDict['ip'];
    $dateStr = $searchDict['date'];
    $stat = $searchDict['stat'];

    // Initialize an empty array to store the log summary data
    $logSummary = [];

    // Iterate over each log file
    foreach ($logFilePaths as $filePath) {
        // Open the log file for reading
        $fileHandle = fopen($filePath, 'r');
        if (!$fileHandle) {
            echo "<p>Failed to open log file: $filePath</p>";
            continue;
        }

        // Read each line from the log file
        while (($line = fgets($fileHandle)) !== false) {
            // Skip lines that don't contain a valid IP address
            if (!preg_match('/([0-9]{1,3}\.){3}[0-9]{1,3}/', $line)) {
                continue;
            }

            $status = getAuthLogStatus($line);
            $data = parseAuthLogLine($line);

            // Extract the timestamp from the auth log entry
            $timeStamp = $data[1];
            
            if ($doSearch) {
                // If $stat is set, check if $status matches $stat
                if ($stat && $status !== $stat) {
                    continue;
                }

                // If $ip is set, check if $data[0] contains $ip
                if ($ip && strpos($data[0], $ip) === false) {
                    continue;
                }

                // If $dateStr is set, check if $data[1] contains $dateStr
                if ($dateStr && strpos($timeStamp, $dateStr) === false) {
                    continue;
                }

                // If $search is set, check if $line contains $search
                if ($search && strpos($line, $search) === false) {
                    continue;
                }
            }

            // Convert the timestamp to a DateTime object
            $date = DateTime::createFromFormat('d/M/Y:H:i:s', $timeStamp);

            // Check if the DateTime object was created successfully
            if ($date !== false) {
                // Get the date in the format YYYY-MM-DD
                $dayOfYear = $date->format('Y-m-d');
                // Get the hour of the day
                $hour = $date->format('G'); // 24-hour format without leading zeros
            } else {
                echo "<p>Invalid timestamp format encountered: $timeStamp</p>";
                continue;
            }

            $hStr = hourStr($hour);
            $logSummary[$dayOfYear][$hStr] = ($logSummary[$dayOfYear][$hStr] ?? 0) + 1;
        }

        // Close the file handle
        fclose($fileHandle);
    }

    // Echo the log summary data as JSON
    echo json_encode($logSummary);
}

// Hour integer to string conversion function
function hourStr($hour)
{
    return str_pad($hour, 2, "0", STR_PAD_LEFT);
}
