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

// Take CLF date format and convert to auth.log date format
function convertCLFDate($date)
{
    // Convert the month number to a three-letter month string
    $monthNum = intval(substr($date, 3, 2));
    $monthStr = date('M', mktime(0, 0, 0, $monthNum, 1));

    // Extract the day, hour, minute, and second from the date
    $day = substr($date, 0, 2);
    $hour = substr($date, 6, 2);
    $minute = substr($date, 9, 2);
    $second = substr($date, 12, 2);

    // Return the date in auth.log format
    return "$day/$monthStr $hour:$minute:$second";
}

// Parse auth log file into standard format
function parseAuthLogLine($line)
{
    // Current year
    $year = date('Y');

    // Check to see if the first character is a letter or a number (to determine
    // which kind of time stamp is used)
    if (preg_match('/^[a-zA-Z]/', $line)) {
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
    } else {
        // handle timespace of the type 2017-12-31T23:59:59.999999-00:00
        if (!preg_match('/(\d+)-(\d+)-(\d+)T(\d+):(\d+):(\d+)/', $line, $matches)) {
            return false; // handle error as appropriate
        }
        $year = $matches[1];
        $month = $matches[2];
        $day = $matches[3];
        $hour = $matches[4];
        $minute = $matches[5];
        $second = $matches[6];

        // Convert the month number to a three-letter month string
        $monthNum = intval($month);
        $monthStr = date('M', mktime(0, 0, 0, $monthNum, 1));
    }

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
    return [$ip, "$day/$monthStr/$year:$hour:$minute:$second", $message];
}

?>