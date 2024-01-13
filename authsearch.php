<?php

// Include the authparse.php file
include 'authparse.php';

// Parameters
$maxResults = 1024;
$logFilePath = '/auth.log';

// Get search term from URL
$searchTerm = $_GET['term'] ?? '';
if ($searchTerm == '') {
    echo json_encode([]);
    exit;
}

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

// generate UNIX grep command for search term
$grepSearchCmd = "grep '$searchTerm'";

// build UNIX command to get the last $linesPerPage lines
$cmd = "$grepSrvCmd | $grepIPCmd | $grepSearchCmd | tail -n $maxResults";

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
