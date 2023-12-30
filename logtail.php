<?php

// Get parameters from URL
$page = $_GET['page'] ?? 0;
$linesPerPage = $_GET['n'] ?? 20;

// Path to the CLF log file
$logFilePath = '/access.log';

// IP addresses to exclude from counts
include 'exclude.php';
$excludedIPs = getExcludedIPs();

// compute the first and last line numbers
$firstLine = $page * $linesPerPage + 1;
$lastLine = $firstLine + ($linesPerPage - 1);

// generate UNIX sed command to remove all lines containing any of the excluded IPs
$sedCmd = '';
foreach ($excludedIPs as $ip) {
    $sedCmd .= "sed '/$ip/d' | ";
}

// read the file in reverse using fast unix tools
$escFilePath = escapeshellarg($logFilePath);
$tailCmd = "tail -n $lastLine | head -n $linesPerPage";

// put everything together into a single command
$cmd = "cat $escFilePath | $sedCmd $tailCmd";
$fp = popen($cmd, 'r');

// read the lines from the pipe
$lines = [];
while ($line = fgets($fp)) {
    $lines[] = $line;
}

// Read in CLF header name array from clfhead.json
$headers = json_decode(file_get_contents('clfhead.json'));

// Create array of CLF log lines
$logLines = [];
$logLines[] = $headers;

// Process each line and add to the array
foreach ($lines as $line) {
    preg_match('/(\S+) \S+ \S+ \[(.+?)\] \"(.*?)\" (\S+) (\S+)/', $line, $matches);
    // Go through each match and add to the array with htmlspecialchars()
    $logLines[] = array_map('htmlspecialchars', array_slice($matches, 1));
}

// Output the array as JSON
echo json_encode($logLines);

?>
