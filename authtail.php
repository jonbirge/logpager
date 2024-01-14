<?php

// Include the authparse.php file
include 'authparse.php';

// Path to the auth log file
$logFilePath = '/auth.log';

// Get parameters from URL
$search = $_GET['search'] ?? null;
$page = $_GET['page'] ?? 0;  // ignored for search
$linesPerPage = $_GET['n'] ?? 16;

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

// build UNIX command
if ($search) {
    $escSearch = escapeshellarg($search);
    $srchCmd .= "grep $escSearch";
    $cmd = "$grepSrvCmd | $grepIPCmd | $srchCmd | tail -n $linesPerPage | tac";
} else {
    // compute the first and last line numbers
    $firstLine = $page * $linesPerPage + 1;
    $lastLine = $firstLine + ($linesPerPage - 1);
    $cmd = "$grepSrvCmd | $grepIPCmd | tail -n $lastLine | head -n $linesPerPage | tac";
}

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
$failedWords = ['failed', 'invalid', 'Unable', '[preauth]'];

// Process each line and add to the array
foreach ($lines as $line) {
    $data = parseAuthLogLine($line);

    if ($data === false) {
        $logLines[] = ['-', '-', $line, 'ERROR'];
        continue;
    }

    // determine status based on $data[2]
    $status = getAuthLogStatus($data[2]);

    $logLines[] = [$data[0], $data[1], $data[2], $status];
}

pclose($fp);

// Output the array as JSON
echo json_encode($logLines);
