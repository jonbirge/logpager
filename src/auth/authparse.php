<?php

// Return list of auth log files
function getAuthLogFiles()
{
    // Array of log files to read
    $logFilePaths = ['/auth.log.1', '/auth.log'];

    // Remove any log files that don't exist
    foreach ($logFilePaths as $key => $logFilePath) {
        if (!file_exists($logFilePath)) {
            unset($logFilePaths[$key]);
        }
    }

    return $logFilePaths;
}

// Determine status of auth log line
function getAuthLogStatus($line)
{
    // Array of words indicating a failed login attempt
    $failedWords = ['reset', 'failed', 'invalid', 'Unable', '[preauth]', 'Connection closed'];
    $successWords = ['Accepted', 'success', 'publickey'];

    // check if $line contains any of the failed words
    $status = 'INFO';
    
    foreach ($failedWords as $word) {
        if (stripos($line, $word) !== false) {
            $status = 'FAIL';
            return $status;
        }
    }

    foreach ($successWords as $word) {
        if (stripos($line, $word) !== false) {
            $status = 'OK';
            return $status;
        }
    }

    return $status;
}

// Parse auth log file into standard format
function parseAuthLogLine($line, $year)
{
    // Determine the kind of time stamp used
    if (($line[0] >= 'A' && $line[0] <= 'Z') || ($line[0] >= 'a' && $line[0] <= 'z')) {  // old CLF-type date format
        // Extract the month, day, and time from the line
        if (!preg_match('/(\S+)\s+(\d+) (\d+):(\d+):(\d+)/', $line, $matches)) {
            return false; // handle error as appropriate
        }
        $monthStr = $matches[1];
        $day = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
        $hour = str_pad($matches[3], 2, '0', STR_PAD_LEFT);
        $minute = str_pad($matches[4], 2, '0', STR_PAD_LEFT);
        $second = str_pad($matches[5], 2, '0', STR_PAD_LEFT);

        // Convert the month to a number
        $dateInfo = date_parse($monthStr);
        $monthNum = $dateInfo['month'];
    } else {  // auth format
        // Split $line at the first space
        $parts = explode(' ', $line, 2); // Limiting to 2 parts ensures only the first space is used for splitting

        // Take the first part, which is before the first space
        $dateTimePart = $parts[0];

        // Now you can parse $dateTimePart with DateTime::createFromFormat
        $date = DateTime::createFromFormat('Y-m-d\TH:i:s.uP', $dateTimePart);

        // convert to UTC
        $date->setTimezone(new DateTimeZone('UTC'));

        // extract the year, month, day, hour, minute, and second
        $year = $date->format('Y');
        $month = $date->format('m');
        $day = $date->format('d');
        $hour = $date->format('H');
        $minute = $date->format('i');
        $second = $date->format('s');

        // Convert the month number to a three-letter month string
        $monthNum = intval($month);
        $monthStr = date('M', mktime(0, 0, 0, $monthNum, 1));
    }

    // Extract the IP address from the line
    if (!preg_match('/(\d+\.\d+\.\d+\.\d+)/', $line, $matches)) {
        $ip = '-';
    } else {
        $ip = $matches[1];
    }

    // Extract the message from the line, excluding anything of the form xyz:
    if (!preg_match('/(?<!\w): (.+)/', $line, $matches)) {
        $message = '-';
    } else {
        $message = $matches[1];
    }

    // Return the array
    return [$ip, "$day/$monthStr/$year:$hour:$minute:$second", $message];
}
