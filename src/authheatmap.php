<?php

// Include the authparse.php file
include 'authparse.php';

function authHeatmap($searchDict)
{
    // Log files to read
    $logFilePaths = getAuthLogFiles();

    // Get search parameters
    $search = $searchDict['search'];
    $ip = $searchDict['ip'];
    $dateStr = $searchDict['date'];
    $stat = $searchDict['stat'];

    // generate UNIX grep command line argument to only include lines containing IP addresses
    $grepIPCmd = "grep -E '([0-9]{1,3}\.){3}[0-9]{1,3}'";

    // generate UNIX grep command line arguments to include services we care about
    $services = ['sshd', 'sudo'];
    $grepArgs = '';
    foreach ($services as $service) {
        $grepArgs .= " -e $service";
    }
    $grepSrvCmd = "grep $grepArgs";

    // generate cat command to concatenate all log files
    $catCmd = 'cat ' . implode(' ', $logFilePaths);

    // build UNIX command to get lines
    $cmd = "$catCmd | $grepSrvCmd | $grepIPCmd";

    // execute the UNIX command
    $fp = popen($cmd, 'r');

    // Initialize an empty array to store the log summary data
    $logSummary = [];

    // Add each failed login attempt to the log summary
    while (($line = fgets($fp)) !== false) {
        $status = getAuthLogStatus($line);
        $data = parseAuthLogLine($line);

        // Extract the timestamp from the auth log entry
        $timeStamp = $data[1];

        // Convert the timestamp to a DateTime object
        $date = DateTime::createFromFormat('d/M/Y:H:i:s', $timeStamp);

        // If $stat is set, check if $status matches $stat
        if ($stat) {
            if ($status !== $stat) {
                continue;
            }
        }

        // If $ip is set, check if $data[0] contains $ip
        if ($ip) {
            if (strpos($data[0], $ip) === false) {
                continue;
            }
        }

        // If $date is set, check if $data[1] contains $date
        if ($date) {
            if (strpos($data[1], $dateStr) === false) {
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

    pclose($fp);

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
