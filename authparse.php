<?php

// Determine status of auth log line
function getAuthLogStatus($line)
{
    // Array of words indicating a failed login attempt
    $failedWords = ['failed', 'invalid', 'Unable', '[preauth]'];

    // check if $line contains any of the failed words
    $status = 'OK';
    foreach ($failedWords as $word) {
        if (stripos($line, $word) !== false) {
            $status = 'FAIL';
            break;
        }
    }

    return $status;
}

// Parse auth log file into standard format
function parseAuthLogLine($line)
{
    // Current year
    $year = date('Y');

    // Extract the month, day, and time from the line
    if (!preg_match('/(\S+)\s+(\d+) (\d+):(\d+):(\d+)/', $line, $matches)) {
        return false; // handle error as appropriate
    }
    $month = $matches[1];
    $day = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
    $hour = str_pad($matches[3], 2, '0', STR_PAD_LEFT);
    $minute = str_pad($matches[4], 2, '0', STR_PAD_LEFT);
    $second = str_pad($matches[5], 2, '0', STR_PAD_LEFT);

    // Convert the month to a number
    $dateInfo = date_parse($month);
    $monthNum = $dateInfo['month'];

    // Infer the year from the month
    if ($monthNum > date('n')) {
        $year--;
    }

    // Extract the IP address from the line
    if (!preg_match('/(\d+\.\d+\.\d+\.\d+)/', $line, $matches)) {
        $ip = '-';
    } else {
        $ip = $matches[1];
    }

    // Extract the message from the line
    if (!preg_match('/\S+ \S+ \S+ \S+ (.+)/', $line, $matches)) {
        $message = '';
    } else {
        $message = $matches[1];
    }

    // Return the array
    return [$ip, "$day/$month/$year:$hour:$minute:$second", $message];
}

?>