<?php

// Include the authparse.php file
include 'authparse.php';

// Get parameters from URL
$search = $_GET['search'] ?? null;  // search string
$page = $_GET['page'] ?? 0;
$linesPerPage = $_GET['n'] ?? 16;

// Path to the auth log file
$logFilePaths = ['/auth.log.1', '/auth.log'];

// Remove any log files that don't exist
foreach ($logFilePaths as $key => $logFilePath) {
    if (!file_exists($logFilePath)) {
        unset($logFilePaths[$key]);
    }
}

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

// build UNIX command
if (!$search) {
    // compute the first and last line numbers
    $firstLine = $page * $linesPerPage + 1;
    $lastLine = $firstLine + ($linesPerPage - 1);
    $cmd = "$catCmd | $grepSrvCmd | $grepIPCmd | tail -n $lastLine | head -n $linesPerPage | tac";
} else {
    $cmd = "$catCmd | $grepSrvCmd | $grepIPCmd | tail -n $linesPerPage | tac";
}

// execute the UNIX command
$fp = popen($cmd, 'r');

// read the lines from UNIX pipe
$lines = [];
while ($line = fgets($fp)) {
    $lines[] = $line;
}

pclose($fp);

// Read in CLF header name array from clfhead.json
$headers = json_decode(file_get_contents('loghead.json'));

// Create array of auth log lines
$logLines = [];
$logLines[] = $headers;

// Check $search string for terms preceded by ip: or date:, and assume there is,
// at most, one of each. Remove the terms from $string and set $ip and $date to
// the values. If $string is empty afterwards, set it to null.
$ip = null;
$date = null;
if ($search) {
    $search = trim($search);
    $ipPos = strpos($search, 'ip:');
    $datePos = strpos($search, 'date:');
    if ($ipPos !== false) {
        $ip = substr($search, $ipPos + 3);
        $search = trim(substr($search, 0, $ipPos));
    }
    if ($datePos !== false) {
        $date = substr($search, $datePos + 5);
        $search = trim(substr($search, 0, $datePos));
    }
    if ($search === '') {
        $search = null;
    }
}

// Process each line and add to the array
foreach ($lines as $line) {
    $data = parseAuthLogLine($line);

    if ($data === false) {
        $logLines[] = ['-', '-', $line, 'ERROR'];
        continue;
    }

    // If $search is set, check if $data[2] contains $search
    if ($search) {
        if (strpos($data[2], $search) === false) {
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
        if (strpos($data[1], $date) === false) {
            continue;
        }
    }

    // determine status based on $data[2]
    $status = getAuthLogStatus($data[2]);

    $logLines[] = [$data[0], $data[1], $data[2], $status];
}

// Output the array as JSON
echo json_encode($logLines);
