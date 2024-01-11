<?php

// Path to the auth log file
$logFilePath = '/auth.log';

// Get parameters from URL
$page = $_GET['page'] ?? 0;
$linesPerPage = $_GET['n'] ?? 20;

// compute the first and last line numbers
$firstLine = $page * $linesPerPage + 1;
$lastLine = $firstLine + ($linesPerPage - 1);

// generate UNIX grep command line argument to only include lines containing IP addresses
$escFilePath = escapeshellarg($logFilePath);
$grepIPCmd = "grep -E '([0-9]{1,3}\.){3}[0-9]{1,3}' $escFilePath";

// generate UNIX grep command line arguments to include services we care about
$services = ['sshd', 'sudo'];
$grepArgs = '';
foreach ($services as $service) {
    $grepArgs .= " -e $service";
}
$escFilePath = escapeshellarg($logFilePath);
$grepSrvCmd = "grep $grepArgs $escFilePath";

// build UNIX command to get the last $linesPerPage lines
$cmd = "$grepSrvCmd | $grepIPCmd | tail -n $lastLine | head -n $linesPerPage";

// execute the UNIX command
$fp = popen($cmd, 'r');

// read the lines from UNIX pipe
$lines = [];
while ($line = fgets($fp)) {
    $lines[] = $line;
}

// Read in CLF header name array from clfhead.json
$headers = json_decode(file_get_contents('loghead.json'));

// Create array of auth log lines
$logLines = [];
$logLines[] = $headers;

// Array of words indicating a failed login attempt
$failedWords = ['failed', 'invalid', 'Unable'];

// Process each line and add to the array
foreach ($lines as $line) {
    $data = parseAuthLogLine($line);

    if ($data === false) {
        $logLines[] = ['-', '-', $line, 'ERROR'];
        continue;
    }

    // check if $data[3] contains any of the failed words
    $status = 'OK';
    foreach ($failedWords as $word) {
        if (stripos($data[2], $word) !== false) {
            $status = 'FAIL';
            break;
        }
    }

    $logLines[] = [$data[0], $data[1], $data[2], $status];
}

// Output the array as JSON
echo json_encode($logLines);


// Function to take a line of an auth log of the form "Dec 24 02:28:16 host
// sshd[441056]: Received disconnect from 173.48.140.140 port..." and transform
// to an array of the form [IP, 12/24:02:28:16, host, sshd: Received
// disconnect from...] where IP is any IP address that occurs in the line.
function parseAuthLogLine($line) {
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
    // if (!isset($dateInfo['month'])) {
    //     return false; // or handle error as appropriate
    // }
    $monthNum = $dateInfo['month'];
    $monthStr = str_pad($monthNum, 2, '0', STR_PAD_LEFT);

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

    // // Extract the host from the line
    // if (!preg_match('/\S+ (\S+)/', $line, $matches)) {
    //     return false; // or handle error as appropriate
    // }
    // $host = $matches[1];

    // Extract the message from the line
    if (!preg_match('/\S+ \S+ \S+ \S+ (.+)/', $line, $matches)) {
        $message = '';
    } else {
        $message = $matches[1];
    }

    // Return the array
    return [$ip, "$year/$monthStr/$day:$hour:$minute:$second", $message];
}
