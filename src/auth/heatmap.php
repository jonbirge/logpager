<?php

// Include the authparse.php file
include 'authparse.php';

function readLines($fileHandle, $bufferSize = 2 * 1024 * 1024) {
    $lines = [];
    $buffer = '';

    while (!feof($fileHandle)) {
        $buffer .= fread($fileHandle, $bufferSize);

        // Continue reading until the end of the current line
        while (!feof($fileHandle) && substr($buffer, -1) !== "\n") {
            $buffer .= fread($fileHandle, 1);
        }

        // Split buffer into lines
        $bufferLines = explode("\n", $buffer);

        // Keep the last partial line in the buffer
        $buffer = array_pop($bufferLines);

        foreach ($bufferLines as $line) {
            $lines[] = $line;
        }
    }

    // Add any remaining partial line if not empty
    if (!empty($buffer)) {
        $lines[] = $buffer;
    }

    return $lines;
}


// Hour integer to string conversion function
function hourStr($hour)
{
    return str_pad($hour, 2, "0", STR_PAD_LEFT);
}

function heatmap($searchDict)
{
    // set $doSearch to false if $searchDict is empty
    $doSearch = !empty($searchDict);

    // Current year
    $year = date('Y');

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

        // Read the log file in chunks
        while (!feof($fileHandle)) {
            // Read some lines from the log file
            $lines = readLines($fileHandle);

            foreach ($lines as $line) {
                // Skip lines that aren't sshd-related
                if (strpos($line, 'sshd') === false) {
                    continue;
                }

                // Skip lines that don't contain a valid IP address
                if (!preg_match('/([0-9]{1,3}\.){3}[0-9]{1,3}/', $line)) {
                    continue;
                }

                // Extract the timestamp from the auth log entry
                $data = parseAuthLogLine($line, $year);
                $timeStamp = $data[1];

                if ($doSearch) {
                    $status = getAuthLogStatus($line);

                    // Check if $status matches $stat exactly
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
        }

        // Close the file handle
        fclose($fileHandle);
    }

    // Echo the log summary data as JSON
    echo json_encode($logSummary);

    // Create a JSON object that includes the log summary and the time of its generation
    $cacheData = [
        'generatedAt' => date('Y-m-d H:i:s'),
        'logSummary' => $logSummary
    ];

    // Write the JSON object to a cache file in /tmp
    file_put_contents('/tmp/auth_heatmap_cache.json', json_encode($cacheData));
}
